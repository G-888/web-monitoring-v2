<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.45; }
        .page-break { page-break-before: always; }
        h1 { font-size: 28px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 22px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        h3 { font-size: 13px; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #475569; font-size: 10px; text-transform: uppercase; }
        .cover { padding: 80px 0 50px; }
        .muted { color: #64748b; }
        .pill { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #fff7ed; color: #c2410c; font-weight: bold; }
        .grid { width: 100%; }
        .metric { display: inline-block; width: 23%; margin: 0 1% 10px 0; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .metric strong { display: block; font-size: 16px; margin-top: 4px; }
    </style>
</head>
<body>
    @include('reports.maintenance.report-body', ['report' => $report, 'summary' => $summary, 'pdf' => true])
</body>
</html>
