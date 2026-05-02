<?php

namespace App\Http\Controllers;

use App\Models\LogInspection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogInspectionController extends Controller
{
    private const MAX_UPLOAD_KB = 102400;
    private const PREVIEW_LINES = 400;

    public function index()
    {
        $inspections = LogInspection::where('user_id', auth()->id())
            ->latest('inspected_at')
            ->take(20)
            ->get();

        return view('log-inspections.index', compact('inspections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'log_file' => [
                'required',
                'file',
                'max:'.self::MAX_UPLOAD_KB,
            ],
        ], [
            'log_file.required' => 'Please select a log file to upload.',
            'log_file.file' => 'The selected upload is not a valid file.',
            'log_file.max' => 'The selected file is too large. Maximum allowed size is 100MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('log_file');
        $this->assertUploadIsSafe($file);

        $storedPath = null;

        try {
            $storedPath = $file->store('log-inspections/'.auth()->id(), 'local');
            $analysis = $this->analyzeFile($storedPath);

            $inspection = LogInspection::create([
                'user_id' => auth()->id(),
                'original_filename' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'stored_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'source_type' => $this->detectSourceType($file->getClientOriginalExtension()),
                'size_bytes' => $file->getSize(),
                'total_lines' => $analysis['total_lines'],
                'critical_count' => $analysis['critical_count'],
                'error_count' => $analysis['error_count'],
                'warning_count' => $analysis['warning_count'],
                'info_count' => $analysis['info_count'],
                'highlights' => $analysis['highlights'],
                'inspected_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if (is_string($storedPath) && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'log_file' => 'Upload failed: '.$exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('log-inspections.show', $inspection)
            ->with('success', 'Upload succeeded: '.$inspection->original_filename.' was analyzed successfully.');
    }

    public function show(LogInspection $logInspection)
    {
        abort_unless($logInspection->user_id === auth()->id(), 403);

        $startLine = max((int) request()->integer('start_line', 1), 1);
        $preview = $this->readPreviewLines($logInspection->stored_path, $startLine, self::PREVIEW_LINES);
        $providers = $this->availableAiProviders();

        return view('log-inspections.show', [
            'logInspection' => $logInspection,
            'preview' => $preview,
            'providers' => $providers,
        ]);
    }

    public function analyzeWithAi(Request $request, LogInspection $logInspection)
    {
        abort_unless($logInspection->user_id === auth()->id(), 403);

        $providerKeys = implode(',', array_keys($this->availableAiProviders()));
        $request->validate([
            'provider' => 'nullable|in:'.$providerKeys,
            'auto_fallback' => 'nullable|boolean',
        ]);

        $serviceConfig = config('services.log_ai');
        $enabled = (bool) ($serviceConfig['enabled'] ?? false);
        $selectedProvider = (string) ($request->input('provider') ?: ($serviceConfig['default_provider'] ?? 'openrouter_free'));
        $autoFallback = $request->boolean('auto_fallback', (bool) ($serviceConfig['fallback_enabled'] ?? true));

        if (! $enabled) {
            return back()->withErrors([
                'log_file' => 'AI analysis is disabled. Set LOG_AI_ENABLED=true in .env.',
            ]);
        }

        $logInspection->update([
            'ai_status' => 'processing',
            'ai_provider' => $selectedProvider,
        ]);

        $candidates = $this->providerAttemptOrder($selectedProvider, $autoFallback);
        $attemptErrors = [];
        $usedProvider = $selectedProvider;

        try {
            $result = null;
            $usedModel = 'unknown';

            foreach ($candidates as $providerName) {
                $providerConfig = $this->providerConfig($providerName, $serviceConfig);
                $apiKey = (string) ($providerConfig['api_key'] ?? '');

                if ($apiKey === '') {
                    $attemptErrors[] = strtoupper($providerName).': missing API key configuration';
                    continue;
                }

                try {
                    $result = $this->requestAiLogAnalysis($logInspection, $providerConfig, $serviceConfig);
                    $usedProvider = $providerName;
                    $usedModel = (string) ($providerConfig['model'] ?? 'unknown');
                    break;
                } catch (\Throwable $providerError) {
                    $attemptErrors[] = strtoupper($providerName).': '.$providerError->getMessage();
                }
            }

            if (! is_array($result)) {
                throw new \RuntimeException('All provider attempts failed. '.implode(' | ', $attemptErrors));
            }

            $logInspection->update([
                'ai_status' => 'completed',
                'ai_provider' => $usedProvider,
                'ai_model' => $usedModel,
                'ai_summary' => $result['summary'],
                'ai_findings' => $result['findings'],
                'ai_analyzed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $logInspection->update([
                'ai_status' => 'failed',
                'ai_summary' => Str::limit($exception->getMessage(), 500),
                'ai_analyzed_at' => now(),
            ]);

            return back()->withErrors([
                'log_file' => 'AI analysis failed. '.$exception->getMessage(),
            ]);
        }

        $fallbackNotice = $usedProvider !== $selectedProvider
            ? ' (fallback from '.strtoupper($selectedProvider).' to '.strtoupper($usedProvider).')'
            : '';

        return back()->with('success', 'AI log analysis completed via '.strtoupper($usedProvider).$fallbackNotice.'.');
    }

    private function analyzeFile(string $storedPath): array
    {
        $content = Storage::disk('local')->get($storedPath);

        $decoded = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        if ($this->appearsBinary($decoded)) {
            return [
                'total_lines' => 0,
                'critical_count' => 0,
                'error_count' => 0,
                'warning_count' => 0,
                'info_count' => 0,
                'highlights' => [
                    [
                        'level' => 'info',
                        'line' => null,
                        'text' => 'Binary log format detected (for example .evtx). Export it to text/XML to inspect detailed events.',
                    ],
                ],
            ];
        }

        $lines = preg_split("/\r\n|\n|\r/", $decoded) ?: [];
        $criticalCount = 0;
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $highlights = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $level = $this->extractLevel($trimmed);
            if (! $level) {
                continue;
            }

            if ($level === 'critical') {
                $criticalCount++;
            } elseif ($level === 'error') {
                $errorCount++;
            } elseif ($level === 'warning') {
                $warningCount++;
            } else {
                $infoCount++;
            }

            if (count($highlights) < 80 && in_array($level, ['critical', 'error', 'warning'], true)) {
                $highlights[] = [
                    'level' => $level,
                    'line' => $lineNumber,
                    'text' => Str::limit($trimmed, 260),
                ];
            }
        }

        return [
            'total_lines' => count($lines),
            'critical_count' => $criticalCount,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'highlights' => $highlights,
        ];
    }

    private function extractLevel(string $line): ?string
    {
        $criticalPattern = '/\b(critical|fatal|panic|emergency|bugcheck|kernel-power)\b/i';
        $errorPattern = '/\b(error|failed|exception|fault|stacktrace)\b/i';
        $warningPattern = '/\b(warn|warning|timeout|retry|degraded)\b/i';
        $infoPattern = '/\b(info|notice|started|completed|success)\b/i';

        if (preg_match($criticalPattern, $line)) {
            return 'critical';
        }

        if (preg_match($errorPattern, $line)) {
            return 'error';
        }

        if (preg_match($warningPattern, $line)) {
            return 'warning';
        }

        if (preg_match($infoPattern, $line)) {
            return 'info';
        }

        return null;
    }

    private function detectSourceType(string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'evtx' => 'windows-event-log',
            'iis', 'w3c' => 'iis-log',
            'xml' => 'xml-log',
            'json' => 'json-log',
            'sql', 'trc' => 'database-log',
            'cfm', 'coldfusion' => 'coldfusion-log',
            default => 'text-log',
        };
    }

    private function appearsBinary(string $content): bool
    {
        $sample = substr($content, 0, 2048);

        if ($sample === '') {
            return false;
        }

        $nonPrintable = preg_match_all('/[^\P{C}\t\r\n]/u', $sample);
        $ratio = $nonPrintable / max(strlen($sample), 1);

        return $ratio > 0.15;
    }

    private function assertUploadIsSafe(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $detectedMime = strtolower((string) $file->getMimeType());
        $realPath = $file->getRealPath();
        $sample = is_string($realPath)
            ? (file_get_contents($realPath, false, null, 0, 4096) ?: '')
            : '';

        $blockedExtensions = [
            'php', 'php5', 'php8', 'phtml', 'phar', 'js', 'exe', 'dll', 'sh', 'bat', 'cmd', 'ps1', 'com', 'msi', 'shtml', 'cgi', 'pl', 'py'
        ];

        if (in_array($extension, $blockedExtensions, true)) {
            throw ValidationException::withMessages([
                'log_file' => 'Executable/script file types are blocked for security.',
            ]);
        }

        $binaryAllowedExtensions = [
            'evtx',
        ];

        $textMimes = [
            'text/plain',
            'text/csv',
            'text/xml',
            'application/xml',
            'application/json',
            'application/x-ndjson',
            'application/csv',
            'application/log',
            'application/vnd.ms-excel',
            'application/octet-stream',
            '',
        ];

        if (! in_array($detectedMime, $textMimes, true) && ! in_array($extension, $binaryAllowedExtensions, true)) {
            throw ValidationException::withMessages([
                'log_file' => 'Unsupported file type ('.$detectedMime.'). Upload text-based logs, or EVTX for Windows Event Logs.',
            ]);
        }

        if (
            ! in_array($extension, $binaryAllowedExtensions, true)
            && $this->appearsBinaryBytes($sample)
        ) {
            throw ValidationException::withMessages([
                'log_file' => 'Binary file detected. Please upload text-based logs (for Windows Event Log use .evtx or exported .xml/.txt).',
            ]);
        }

        $lowerSample = strtolower($sample);
        if (
            ! in_array($extension, $binaryAllowedExtensions, true)
            && (str_contains($lowerSample, '<?php') || str_contains($lowerSample, '<?=') || str_contains($lowerSample, '<script'))
        ) {
            throw ValidationException::withMessages([
                'log_file' => 'Potential executable/script content detected. Upload blocked for security.',
            ]);
        }
    }

    private function readPreviewLines(string $storedPath, int $startLine, int $maxLines): array
    {
        $fullPath = Storage::disk('local')->path($storedPath);
        if (! is_file($fullPath)) {
            return [
                'start_line' => $startLine,
                'end_line' => $startLine - 1,
                'lines' => [],
                'has_next' => false,
            ];
        }

        $file = new \SplFileObject($fullPath, 'r');
        $current = 1;
        $collected = [];

        while (! $file->eof()) {
            $line = $file->fgets();

            if ($current >= $startLine && count($collected) < $maxLines) {
                $collected[] = [
                    'number' => $current,
                    'text' => rtrim(mb_convert_encoding($line, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252'), "\r\n"),
                ];
            }

            if (count($collected) >= $maxLines) {
                break;
            }

            $current++;
        }

        return [
            'start_line' => $startLine,
            'end_line' => $startLine + max(count($collected) - 1, 0),
            'lines' => $collected,
            'has_next' => ! $file->eof(),
        ];
    }

    private function requestAiLogAnalysis(LogInspection $logInspection, array $providerConfig, array $serviceConfig): array
    {
        $preview = $this->readPreviewLines($logInspection->stored_path, 1, 200);
        $previewText = collect($preview['lines'])
            ->map(fn (array $line) => $line['number'].': '.$line['text'])
            ->implode("\n");

        $payload = [
            'model' => $providerConfig['model'] ?? 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a security and reliability log analyst. Return only valid JSON with keys: summary (string), findings (array of objects with severity, category, detail, recommendation).',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'file' => $logInspection->original_filename,
                        'source_type' => $logInspection->source_type,
                        'stats' => [
                            'critical' => $logInspection->critical_count,
                            'error' => $logInspection->error_count,
                            'warning' => $logInspection->warning_count,
                            'info' => $logInspection->info_count,
                            'total_lines' => $logInspection->total_lines,
                        ],
                        'highlights' => $logInspection->highlights ?? [],
                        'preview_lines' => $previewText,
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ],
            'temperature' => 0.2,
        ];

        $baseUrl = rtrim((string) ($providerConfig['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $verifySsl = (bool) ($serviceConfig['verify_ssl'] ?? true);
        
        $request = Http::timeout((int) ($serviceConfig['timeout'] ?? 30))
            ->withToken((string) $providerConfig['api_key'])
            ->acceptJson();

        if (! $verifySsl) {
            $request->withoutVerifying();
        }

        $response = $request->post($baseUrl.'/chat/completions', $payload)
            ->throw()
            ->json();

        $content = data_get($response, 'choices.0.message.content', '{}');
        if (! is_string($content) || $content === '') {
            throw new \RuntimeException('AI provider returned empty response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $decoded = json_decode($this->extractJsonObject($content), true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response is not valid JSON.');
        }

        $summary = (string) ($decoded['summary'] ?? 'No summary provided.');
        $findings = $decoded['findings'] ?? [];

        if (! is_array($findings)) {
            $findings = [];
        }

        return [
            'summary' => Str::limit($summary, 5000),
            'findings' => array_values(array_filter(array_map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                return [
                    'severity' => (string) ($item['severity'] ?? 'unknown'),
                    'category' => (string) ($item['category'] ?? 'general'),
                    'detail' => (string) ($item['detail'] ?? ''),
                    'recommendation' => (string) ($item['recommendation'] ?? ''),
                ];
            }, $findings))),
        ];
    }

    private function availableAiProviders(): array
    {
        return [
            'openrouter_free' => 'OpenRouter Free',
            'groq_free' => 'Groq Free Tier',
        ];
    }

    private function providerConfig(string $provider, array $serviceConfig): array
    {
        $providers = (array) ($serviceConfig['providers'] ?? []);

        return (array) ($providers[$provider] ?? []);
    }

    private function providerAttemptOrder(string $preferredProvider, bool $autoFallback): array
    {
        $all = array_keys($this->availableAiProviders());
        $order = [$preferredProvider];

        if ($autoFallback) {
            foreach ($all as $candidate) {
                if ($candidate !== $preferredProvider) {
                    $order[] = $candidate;
                }
            }
        }

        return array_values(array_unique($order));
    }

    private function appearsBinaryBytes(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        $nonPrintable = 0;
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            $isControl = ($ord < 32 || $ord === 127) && ! in_array($ord, [9, 10, 13], true);
            if ($isControl) {
                $nonPrintable++;
            }
        }

        return ($nonPrintable / max($length, 1)) > 0.15;
    }

    private function extractJsonObject(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '{}';
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end < $start) {
            return '{}';
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
