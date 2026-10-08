@props(['class' => ''])

<div x-data="{
    init() {
        const top = this.$refs.scrollTop;
        const bot = this.$refs.scrollBot;
        let syncing = false;

        const sync = (source, target) => {
            if (syncing) return;
            syncing = true;
            target.scrollLeft = source.scrollLeft;
            syncing = false;
        };

        top.addEventListener('scroll', () => sync(top, bot));
        bot.addEventListener('scroll', () => sync(bot, top));

        // Match dummy width to content scrollWidth
        const resizeObserver = new ResizeObserver(() => {
            this.$refs.spacer.style.width = bot.scrollWidth + 'px';
            // Hide top scrollbar if content doesn't overflow
            top.style.display = bot.scrollWidth > bot.clientWidth ? '' : 'none';
        });
        resizeObserver.observe(bot);
        if (bot.children[0]) resizeObserver.observe(bot.children[0]);
    }
}" {{ $attributes->merge(['class' => $class]) }}>
    {{-- Top scrollbar (dummy) --}}
    <div x-ref="scrollTop" class="overflow-x-auto overflow-y-hidden" style="height: 12px; margin-bottom: -1px;">
        <div x-ref="spacer" style="height: 1px;">&nbsp;</div>
    </div>
    {{-- Actual content --}}
    <div x-ref="scrollBot" class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
