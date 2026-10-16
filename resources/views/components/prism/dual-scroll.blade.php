@props(['class' => ''])

<div x-data="{
    init() {
        const top = this.$refs.scrollTop;
        const bot = this.$refs.scrollBot;
        const spacer = this.$refs.spacer;
        let syncing = false;
        let lastScrollLeft = 0;

        const sync = (source, target) => {
            if (syncing) return;
            syncing = true;
            target.scrollLeft = source.scrollLeft;
            lastScrollLeft = source.scrollLeft;
            syncing = false;
        };

        top.addEventListener('scroll', () => sync(top, bot));
        bot.addEventListener('scroll', () => sync(bot, top));

        // Horizontal mouse wheel handling:
        // 1. Shift + Mouse Wheel anywhere inside dual-scroll -> horizontal scroll
        // 2. Mouse Wheel on top scrollbar or non-vertical-scroll areas -> horizontal scroll
        const handleWheel = (e) => {
            if (e.shiftKey) {
                e.preventDefault();
                bot.scrollLeft += (e.deltaY || e.deltaX);
                lastScrollLeft = bot.scrollLeft;
                return;
            }

            const targetScrollable = e.target.closest('.overflow-y-auto');
            if (!targetScrollable && e.deltaY !== 0) {
                e.preventDefault();
                bot.scrollLeft += e.deltaY;
                lastScrollLeft = bot.scrollLeft;
            }
        };

        bot.addEventListener('wheel', handleWheel, { passive: false });
        top.addEventListener('wheel', handleWheel, { passive: false });

        const updateWidth = () => {
            if (!bot || !spacer) return;
            const scrollW = bot.scrollWidth;
            const clientW = bot.clientWidth;
            spacer.style.width = scrollW + 'px';
            top.style.display = (scrollW > clientW + 2) ? 'block' : 'none';

            // Restore scroll position if reset by DOM morphing
            if (lastScrollLeft > 0 && Math.abs(bot.scrollLeft - lastScrollLeft) > 5) {
                syncing = true;
                bot.scrollLeft = lastScrollLeft;
                top.scrollLeft = lastScrollLeft;
                syncing = false;
            }
        };

        // ResizeObserver for container & first child
        const resizeObserver = new ResizeObserver(() => {
            requestAnimationFrame(updateWidth);
        });
        resizeObserver.observe(bot);
        if (bot.firstElementChild) {
            resizeObserver.observe(bot.firstElementChild);
        }

        // MutationObserver to catch Livewire DOM updates & card transfers
        const mutationObserver = new MutationObserver(() => {
            requestAnimationFrame(updateWidth);
            if (bot.firstElementChild) {
                resizeObserver.observe(bot.firstElementChild);
            }
        });
        mutationObserver.observe(bot, { childList: true, subtree: true });

        // Livewire morph lifecycle hooks
        if (window.Livewire) {
            Livewire.hook('morph.updated', () => {
                requestAnimationFrame(() => {
                    updateWidth();
                    setTimeout(updateWidth, 50);
                });
            });
            Livewire.hook('commit', () => {
                requestAnimationFrame(updateWidth);
            });
        }

        window.addEventListener('resize', updateWidth);
        document.addEventListener('livewire:navigated', updateWidth);

        // Initial measurement
        this.$nextTick(() => {
            updateWidth();
            setTimeout(updateWidth, 100);
        });
    }
}" {{ $attributes->merge(['class' => 'flex flex-col ' . $class]) }}>
    {{-- Top scrollbar (dummy) --}}
    <div x-ref="scrollTop" class="overflow-x-auto overflow-y-hidden flex-shrink-0" style="height: 12px; margin-bottom: -1px;">
        <div x-ref="spacer" style="height: 1px;">&nbsp;</div>
    </div>
    {{-- Actual content --}}
    <div x-ref="scrollBot" class="flex-1 min-h-0 overflow-x-auto overflow-y-hidden">
        {{ $slot }}
    </div>
</div>
