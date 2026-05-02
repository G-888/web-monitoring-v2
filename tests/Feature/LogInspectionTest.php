<?php

use App\Models\LogInspection;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ]);
});

test('authenticated user can upload and inspect a log file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $content = "INFO service started\nWARNING connection slow\nERROR database timeout\nCRITICAL kernel-power";
    $file = UploadedFile::fake()->createWithContent('system.log', $content);

    $response = $this->actingAs($user)->post(route('log-inspections.store'), [
        'log_file' => $file,
    ]);

    $inspection = LogInspection::first();

    $response->assertRedirect(route('log-inspections.show', $inspection, absolute: false));

    expect($inspection)
        ->user_id->toBe($user->id)
        ->original_filename->toBe('system.log')
        ->critical_count->toBe(1)
        ->error_count->toBe(1)
        ->warning_count->toBe(1)
        ->info_count->toBe(1)
        ->total_lines->toBe(4);
});

test('iis log style file can be uploaded and analyzed', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $content = "#Software: Microsoft Internet Information Services\n2026-05-02 01:00:00 W3SVC1 SERVER 10.0.0.1 GET /index.html - 80 - 203.0.113.10 Mozilla/5.0 200 0 0 123";
    $file = UploadedFile::fake()->createWithContent('u_ex260502.iis', $content);

    $response = $this->actingAs($user)->post(route('log-inspections.store'), [
        'log_file' => $file,
    ]);

    $inspection = LogInspection::first();

    $response->assertRedirect(route('log-inspections.show', $inspection, absolute: false));
    expect($inspection)->source_type->toBe('iis-log');
});

test('users cannot open another users inspection result', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $inspection = LogInspection::create([
        'user_id' => $owner->id,
        'original_filename' => 'app.log',
        'stored_path' => 'log-inspections/sample.log',
        'mime_type' => 'text/plain',
        'source_type' => 'text-log',
        'size_bytes' => 120,
        'total_lines' => 10,
        'critical_count' => 0,
        'error_count' => 1,
        'warning_count' => 2,
        'info_count' => 4,
        'highlights' => [],
        'inspected_at' => now(),
    ]);

    $this->actingAs($other)
        ->get(route('log-inspections.show', $inspection))
        ->assertForbidden();
});

test('upload is rejected when log file exceeds size limit', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('big.log', 110000, 'text/plain');

    $response = $this->from(route('log-inspections.index'))
        ->actingAs($user)
        ->post(route('log-inspections.store'), [
            'log_file' => $file,
        ]);

    $response->assertRedirect(route('log-inspections.index', absolute: false));
    $response->assertSessionHasErrors('log_file');
    expect(LogInspection::count())->toBe(0);
});

test('upload is rejected when potential executable content is detected', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('suspicious.log', "<?php echo 'x';\nERROR test");

    $response = $this->from(route('log-inspections.index'))
        ->actingAs($user)
        ->post(route('log-inspections.store'), [
            'log_file' => $file,
        ]);

    $response->assertRedirect(route('log-inspections.index', absolute: false));
    $response->assertSessionHasErrors('log_file');
    expect(LogInspection::count())->toBe(0);
});

test('user can run ai analysis and view manual content preview', function () {
    Storage::fake('local');
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Potential brute-force pattern detected in authentication failures.',
                            'findings' => [
                                [
                                    'severity' => 'high',
                                    'category' => 'security',
                                    'detail' => 'Repeated failed login attempts observed.',
                                    'recommendation' => 'Rate-limit and monitor suspicious IPs.',
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    config()->set('services.log_ai.enabled', true);
    config()->set('services.log_ai.default_provider', 'openrouter_free');
    config()->set('services.log_ai.timeout', 30);
    config()->set('services.log_ai.providers.openrouter_free.api_key', 'openrouter-test-key');
    config()->set('services.log_ai.providers.openrouter_free.model', 'openrouter/free');
    config()->set('services.log_ai.providers.openrouter_free.base_url', 'https://openrouter.ai/api/v1');
    config()->set('services.log_ai.providers.groq_free.api_key', 'groq-test-key');
    config()->set('services.log_ai.providers.groq_free.model', 'llama-3.1-8b-instant');
    config()->set('services.log_ai.providers.groq_free.base_url', 'https://api.groq.com/openai/v1');

    $user = User::factory()->create();

    Storage::disk('local')->put('log-inspections/'.$user->id.'/app.log', "INFO start\nERROR bad password\nWARNING retry");

    $inspection = LogInspection::create([
        'user_id' => $user->id,
        'original_filename' => 'app.log',
        'stored_path' => 'log-inspections/'.$user->id.'/app.log',
        'mime_type' => 'text/plain',
        'source_type' => 'text-log',
        'size_bytes' => 42,
        'total_lines' => 3,
        'critical_count' => 0,
        'error_count' => 1,
        'warning_count' => 1,
        'info_count' => 1,
        'highlights' => [
            ['level' => 'error', 'line' => 2, 'text' => 'ERROR bad password'],
        ],
        'inspected_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('log-inspections.ai-analyze', $inspection))
        ->assertRedirect();

    expect($inspection->refresh())
        ->ai_status->toBe('completed')
        ->ai_provider->toBe('openrouter_free')
        ->ai_model->toBe('openrouter/free');

    $this->actingAs($user)
        ->get(route('log-inspections.show', $inspection))
        ->assertOk()
        ->assertSee('Manual Log Content Inspection')
        ->assertSee('ERROR bad password')
        ->assertSee('AI Summary');
});

test('user can choose groq free provider for ai analysis', function () {
    Storage::fake('local');
    Http::fake([
        'https://api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Groq free report generated.',
                            'findings' => [],
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    config()->set('services.log_ai.enabled', true);
    config()->set('services.log_ai.default_provider', 'openrouter_free');
    config()->set('services.log_ai.timeout', 30);
    config()->set('services.log_ai.providers.openrouter_free.api_key', 'openrouter-test-key');
    config()->set('services.log_ai.providers.openrouter_free.model', 'openrouter/free');
    config()->set('services.log_ai.providers.openrouter_free.base_url', 'https://openrouter.ai/api/v1');
    config()->set('services.log_ai.providers.groq_free.api_key', 'groq-test-key');
    config()->set('services.log_ai.providers.groq_free.model', 'llama-3.1-8b-instant');
    config()->set('services.log_ai.providers.groq_free.base_url', 'https://api.groq.com/openai/v1');

    $user = User::factory()->create();

    Storage::disk('local')->put('log-inspections/'.$user->id.'/groq.log', "ERROR fail");

    $inspection = LogInspection::create([
        'user_id' => $user->id,
        'original_filename' => 'groq.log',
        'stored_path' => 'log-inspections/'.$user->id.'/groq.log',
        'mime_type' => 'text/plain',
        'source_type' => 'text-log',
        'size_bytes' => 11,
        'total_lines' => 1,
        'critical_count' => 0,
        'error_count' => 1,
        'warning_count' => 0,
        'info_count' => 0,
        'highlights' => [],
        'inspected_at' => now(),
    ]);

    $this->actingAs($user)->post(route('log-inspections.ai-analyze', $inspection), [
        'provider' => 'groq_free',
    ])->assertRedirect();

    expect($inspection->refresh())
        ->ai_status->toBe('completed')
        ->ai_provider->toBe('groq_free')
        ->ai_model->toBe('llama-3.1-8b-instant');
});

test('ai analysis automatically falls back to groq free when openrouter free fails', function () {
    Storage::fake('local');
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'error' => ['message' => 'temporary unavailable'],
        ], 503),
        'https://api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Fallback provider succeeded.',
                            'findings' => [],
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    config()->set('services.log_ai.enabled', true);
    config()->set('services.log_ai.default_provider', 'openrouter_free');
    config()->set('services.log_ai.fallback_enabled', true);
    config()->set('services.log_ai.timeout', 30);
    config()->set('services.log_ai.providers.openrouter_free.api_key', 'openrouter-test-key');
    config()->set('services.log_ai.providers.openrouter_free.model', 'openrouter/free');
    config()->set('services.log_ai.providers.openrouter_free.base_url', 'https://openrouter.ai/api/v1');
    config()->set('services.log_ai.providers.groq_free.api_key', 'groq-test-key');
    config()->set('services.log_ai.providers.groq_free.model', 'llama-3.1-8b-instant');
    config()->set('services.log_ai.providers.groq_free.base_url', 'https://api.groq.com/openai/v1');

    $user = User::factory()->create();
    Storage::disk('local')->put('log-inspections/'.$user->id.'/fallback.log', "ERROR first provider fail");

    $inspection = LogInspection::create([
        'user_id' => $user->id,
        'original_filename' => 'fallback.log',
        'stored_path' => 'log-inspections/'.$user->id.'/fallback.log',
        'mime_type' => 'text/plain',
        'source_type' => 'text-log',
        'size_bytes' => 25,
        'total_lines' => 1,
        'critical_count' => 0,
        'error_count' => 1,
        'warning_count' => 0,
        'info_count' => 0,
        'highlights' => [],
        'inspected_at' => now(),
    ]);

    $this->actingAs($user)->post(route('log-inspections.ai-analyze', $inspection), [
        'provider' => 'openrouter_free',
        'auto_fallback' => '1',
    ])->assertRedirect();

    expect($inspection->refresh())
        ->ai_status->toBe('completed')
        ->ai_provider->toBe('groq_free')
        ->ai_model->toBe('llama-3.1-8b-instant');
});

test('user can choose openrouter free provider for ai analysis', function () {
    Storage::fake('local');
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'OpenRouter free analysis complete.',
                            'findings' => [],
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    config()->set('services.log_ai.enabled', true);
    config()->set('services.log_ai.default_provider', 'openrouter_free');
    config()->set('services.log_ai.timeout', 30);
    config()->set('services.log_ai.providers.openrouter_free.api_key', 'openrouter-test-key');
    config()->set('services.log_ai.providers.openrouter_free.model', 'openrouter/free');
    config()->set('services.log_ai.providers.openrouter_free.base_url', 'https://openrouter.ai/api/v1');

    $user = User::factory()->create();
    Storage::disk('local')->put('log-inspections/'.$user->id.'/free.log', "INFO free model");

    $inspection = LogInspection::create([
        'user_id' => $user->id,
        'original_filename' => 'free.log',
        'stored_path' => 'log-inspections/'.$user->id.'/free.log',
        'mime_type' => 'text/plain',
        'source_type' => 'text-log',
        'size_bytes' => 14,
        'total_lines' => 1,
        'critical_count' => 0,
        'error_count' => 0,
        'warning_count' => 0,
        'info_count' => 1,
        'highlights' => [],
        'inspected_at' => now(),
    ]);

    $this->actingAs($user)->post(route('log-inspections.ai-analyze', $inspection), [
        'provider' => 'openrouter_free',
        'auto_fallback' => '1',
    ])->assertRedirect();

    expect($inspection->refresh())
        ->ai_status->toBe('completed')
        ->ai_provider->toBe('openrouter_free')
        ->ai_model->toBe('openrouter/free');
});
