@props(['class' => ''])

<div x-data="{
    init() {
        const top = this.$refs.scrollTop;
        const bot = this.$refs.scrollBot;
        const spacer = this.$refs.spacer;
        let isTouching = false;
        let rAFScrollSync = null;
        let rAFWidthUpdate = null;
        let lastKnownScrollLeft = 0;

        const isDesktop = () => window.innerWidth >= 768 && !window.matchMedia('(pointer: coarse)').matches;

        // Sync scroll positions between top and bot (Desktop only)
        const sync = (source, target) => {
            if (!isDesktop() || isTouching) return;
            if (rAFScrollSync) cancelAnimationFrame(rAFScrollSync);

            rAFScrollSync = requestAnimationFrame(() => {
                if (Math.abs(target.scrollLeft - source.scrollLeft) > 1) {
                    target.scrollLeft = source.scrollLeft;
                }
                lastKnownScrollLeft = source.scrollLeft;
            });
        };

        if (top) {
            top.addEventListener('scroll', () => sync(top, bot), { passive: true });
        }
        bot.addEventListener('scroll', () => {
            lastKnownScrollLeft = bot.scrollLeft;
            if (top && isDesktop()) {
                sync(bot, top);
            }
        }, { passive: true });

        // Touch event tracking to prevent scroll interruption during gestures
        bot.addEventListener('touchstart', () => { isTouching = true; }, { passive: true });
        bot.addEventListener('touchend', () => {
            isTouching = false;
            lastKnownScrollLeft = bot.scrollLeft;
        }, { passive: true });
        bot.addEventListener('touchcancel', () => { isTouching = false; }, { passive: true });

        // Horizontal mouse wheel handling for desktop:
        // Shift + Wheel on table OR Wheel directly on the top scrollbar -> horizontal scroll
        const handleWheel = (e) => {
            if (e.shiftKey || e.currentTarget === top) {
                e.preventDefault();
                bot.scrollLeft += (e.deltaY || e.deltaX);
                lastKnownScrollLeft = bot.scrollLeft;
            }
        };

        bot.addEventListener('wheel', handleWheel, { passive: false });
        if (top) {
            top.addEventListener('wheel', handleWheel, { passive: false });
        }

        const updateWidth = () => {
            if (!bot || !spacer) return;
            if (rAFWidthUpdate) cancelAnimationFrame(rAFWidthUpdate);

            rAFWidthUpdate = requestAnimationFrame(() => {
                const scrollW = bot.scrollWidth;
                const clientW = bot.clientWidth;
                spacer.style.width = scrollW + 'px';

                if (top) {
                    const shouldShow = isDesktop() && (scrollW > clientW + 2);
                    top.style.display = shouldShow ? 'block' : 'none';
                }

                // Restore scroll position smoothly if reset by Livewire DOM morphing (and user not currently touching)
                if (!isTouching && lastKnownScrollLeft > 0 && Math.abs(bot.scrollLeft - lastKnownScrollLeft) > 10) {
                    bot.scrollLeft = lastKnownScrollLeft;
                    if (top && isDesktop()) {
                        top.scrollLeft = lastKnownScrollLeft;
                    }
                }
            });
        };

        // ResizeObserver for container & first child
        const resizeObserver = new ResizeObserver(() => {
            updateWidth();
        });
        resizeObserver.observe(bot);
        if (bot.firstElementChild) {
            resizeObserver.observe(bot.firstElementChild);
        }

        // Livewire morph lifecycle hooks
        if (window.Livewire) {
            Livewire.hook('morph.updated', () => {
                requestAnimationFrame(updateWidth);
            });
            Livewire.hook('commit', () => {
                requestAnimationFrame(updateWidth);
            });
        }

        window.addEventListener('resize', updateWidth, { passive: true });
        document.addEventListener('livewire:navigated', updateWidth, { passive: true });

        // Initial measurement
        this.$nextTick(() => {
            updateWidth();
        });
    }
}" {{ $attributes->merge(['class' => 'flex flex-col ' . $class]) }}>
    {{-- Top scrollbar (dummy) - Hidden on mobile, visible on desktop with overflow --}}
    <div x-ref="scrollTop" class="hidden md:block overflow-x-auto overflow-y-hidden flex-shrink-0" style="height: 12px; margin-bottom: -1px;">
        <div x-ref="spacer" style="height: 1px;">&nbsp;</div>
    </div>
    {{-- Actual content - fluid touch scrolling --}}
    <div x-ref="scrollBot" class="flex-1 min-h-0 overflow-x-auto overflow-y-hidden overscroll-x-contain" style="-webkit-overflow-scrolling: touch;">
        {{ $slot }}
    </div>
</div>
