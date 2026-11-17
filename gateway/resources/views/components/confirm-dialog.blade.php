{{--
    One accessible confirmation dialog per page, replacing the browser's native confirm().
    Open it with: x-on:click="$dispatch('confirm-action', { message: 'Remove this card?', danger: true, run: () => $wire.method(args) })"
--}}
<dialog
    x-data="{ message: '', confirmLabel: '', danger: false, run: null }"
    x-on:confirm-action.window="message = $event.detail.message; confirmLabel = $event.detail.confirmLabel ?? @js(__('Confirm')); danger = $event.detail.danger ?? false; run = $event.detail.run; $el.showModal(); $nextTick(() => $refs.cancel.focus())"
    x-on:close="run = null"
    aria-labelledby="confirm-dialog-message"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-(--radius-panel) bg-white p-6 text-zinc-900 shadow-(--shadow-lift) backdrop:bg-ink-950/40"
    data-test="confirm-dialog"
>
    <p id="confirm-dialog-message" class="text-base leading-relaxed" x-text="message"></p>
    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary min-h-11" x-ref="cancel" x-on:click="$el.closest('dialog').close()">{{ __('Cancel') }}</button>
        <button
            type="button"
            x-bind:class="danger ? 'inline-flex min-h-11 items-center justify-center rounded-full bg-red-600 px-6 font-medium text-white hover:bg-red-700' : 'btn-primary'"
            x-on:click="const action = run; $el.closest('dialog').close(); action && action()"
            x-text="confirmLabel"
            data-test="confirm-dialog-accept"
        ></button>
    </div>
</dialog>
