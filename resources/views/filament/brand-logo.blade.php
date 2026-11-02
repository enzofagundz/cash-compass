{{--
    Cash Compass brand lockup: compass mark + wordmark.

    Rendered by the panel as the brand logo (`brandLogoHeight('2.5rem')`), so the
    SVG scales from the wrapper height. Colours come from the theme tokens with
    literal fallbacks, which keeps the mark readable on cream and inside the
    authentication card before the stylesheet resolves.
--}}
<span class="cc-brand" role="img" aria-label="Cash Compass">
    <svg
        class="cc-brand-mark"
        viewBox="0 0 48 48"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <circle
            cx="24"
            cy="24"
            r="20.5"
            fill="var(--cc-yellow, #ffdd55)"
            stroke="var(--cc-ink, #16130f)"
            stroke-width="3"
        />
        <path
            d="M24 11 29.5 24 24 37 18.5 24Z"
            fill="var(--cc-ink, #16130f)"
            stroke="var(--cc-ink, #16130f)"
            stroke-width="1.5"
            stroke-linejoin="round"
        />
        <path
            d="M24 11 29.5 24 18.5 24Z"
            fill="var(--cc-red, #ff5252)"
            stroke="var(--cc-ink, #16130f)"
            stroke-width="1.5"
            stroke-linejoin="round"
        />
        <circle
            cx="24"
            cy="24"
            r="3.5"
            fill="var(--cc-paper, #fffdf6)"
            stroke="var(--cc-ink, #16130f)"
            stroke-width="2"
        />
    </svg>

    <span class="cc-brand-word">Cash Compass</span>
</span>
