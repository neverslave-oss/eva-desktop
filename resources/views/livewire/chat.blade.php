<div class="flex flex-col h-full max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-white mb-4">Chat</h2>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto space-y-4 mb-4 bg-gray-900 rounded-xl border border-gray-800 p-4 min-h-[500px]">
        @if (empty($messages))
            <div class="flex items-center justify-center h-full text-gray-500">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-lg font-medium">Start a conversation</p>
                    <p class="text-sm text-gray-600 mt-1">Ask the kernel-evolving agent anything.</p>
                </div>
            </div>
        @else
            @foreach ($messages as $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] {{ $msg['role'] === 'user'
                        ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-700/30'
                        : 'bg-gray-800 text-gray-200 border border-gray-700' }}
                        rounded-lg px-4 py-2">
                        <p class="text-sm">{{ $msg['content'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $msg['timestamp'] }}</p>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <!-- Input -->
    <div class="flex gap-2">
        <input type="text"
               wire:model="message"
               wire:keydown.enter="sendMessage"
               placeholder="Type a message..."
               class="flex-1 bg-gray-900 border border-gray-800 rounded-lg px-4 py-3 text-gray-200 placeholder-gray-600 text-sm focus:outline-none focus:border-emerald-600">
        <button wire:click="sendMessage"
                class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 rounded-lg text-sm font-medium transition-colors"
                @disabled(empty(trim($message)))>
            Send
        </button>
    </div>
</div>
