<x-app-layout>
    <x-slot name="header_title">Alert Channels</x-slot>

    <div class="max-w-4xl mx-auto space-y-8">
        <section class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="px-8 py-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold">Your Alert Channels</h3>
                    <p class="text-sm text-slate-500 mt-1">Configure webhooks and emails for monitor alerts.</p>
                </div>
            </div>

            <div class="p-8">
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 font-medium text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-8">
                    <form method="POST" action="{{ route('alert-channels.store') }}" class="flex gap-4 items-end">
                        @csrf
                        <div class="w-1/4">
                            <label class="block text-sm font-bold mb-2">Type</label>
                            <select name="type" required class="w-full rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-4 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all">
                                <option value="slack">Slack Webhook</option>
                                <option value="discord">Discord Webhook</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-bold mb-2">Endpoint URL / Email</label>
                            <input type="text" name="endpoint" required placeholder="https://hooks.slack.com/... or email@example.com" class="w-full rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-4 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all">
                        </div>
                        <div>
                            <button type="submit" class="h-[42px] px-6 rounded-xl bg-orange-500 text-white font-bold text-sm hover:bg-orange-600 transition-colors">
                                Add Channel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="space-y-4">
                    @forelse($channels as $channel)
                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5">
                            <div>
                                <div class="font-bold uppercase tracking-widest text-xs text-slate-500 mb-1">{{ $channel->type }}</div>
                                <div class="font-medium text-sm break-all">{{ $channel->endpoint }}</div>
                            </div>
                            <div>
                                <form method="POST" action="{{ route('alert-channels.destroy', $channel) }}" onsubmit="return confirm('Delete this channel?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-xs font-bold hover:bg-red-50 dark:border-red-500/20 dark:text-red-400 dark:hover:bg-red-500/10 transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-500 text-sm">
                            No alert channels configured. Add one above.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
