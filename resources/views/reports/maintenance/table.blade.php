@php $pdf = $pdf ?? false; @endphp
<div class="{{ $pdf ? '' : 'mt-3 overflow-x-auto' }}">
    <table class="{{ $pdf ? '' : 'min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10' }}">
        <thead class="{{ $pdf ? '' : 'bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-[#07131f] dark:text-slate-400' }}">
            <tr>
                @foreach($headers as $header)
                    <th class="{{ $pdf ? '' : 'px-4 py-3' }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="{{ $pdf ? '' : 'divide-y divide-slate-200 dark:divide-white/10' }}">
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td class="{{ $pdf ? '' : 'px-4 py-3 text-slate-700 dark:text-slate-200' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" class="{{ $pdf ? '' : 'px-4 py-6 text-center text-slate-500 dark:text-slate-400' }}">No data for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
