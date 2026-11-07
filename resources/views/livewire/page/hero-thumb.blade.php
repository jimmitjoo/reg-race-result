{{-- A small sketch of a hero's look, to choose by. --}}
<span class="relative flex aspect-[16/10] w-full overflow-hidden rounded-[6px] bg-ground ring-1 ring-zinc-200 dark:ring-zinc-700" aria-hidden="true">
    @switch ($variant)
        @case ('brand')
            <span class="m-2 flex flex-1 flex-col justify-end gap-1 rounded-[4px] bg-brand p-2"><span class="h-2 w-3/5 rounded-full bg-white"></span><span class="h-1 w-2/5 rounded-full bg-white/70"></span></span>
            @break
        @case ('brand-full')
            <span class="flex flex-1 flex-col justify-center gap-1 bg-brand px-3"><span class="h-2.5 w-3/5 rounded-full bg-white"></span><span class="h-1 w-2/5 rounded-full bg-white/70"></span></span>
            @break
        @case ('image-full')
            <span class="flex flex-1 flex-col justify-end gap-1 bg-linear-to-br from-zinc-400 to-zinc-700 p-2"><span class="h-2.5 w-3/5 rounded-full bg-white"></span><span class="h-1 w-2/5 rounded-full bg-white/70"></span></span>
            @break
        @case ('split')
            <span class="m-2 grid flex-1 grid-cols-2 overflow-hidden rounded-[4px] bg-white"><span class="flex flex-col justify-center gap-1 p-1.5"><span class="h-1.5 w-4/5 rounded-full bg-zinc-800"></span><span class="h-1.5 w-1/2 rounded-full bg-brand"></span></span><span class="bg-linear-to-br from-zinc-400 to-zinc-600"></span></span>
            @break
        @case ('split-full')
            <span class="grid flex-1 grid-cols-2"><span class="flex flex-col justify-center gap-1 bg-brand px-2"><span class="h-2 w-4/5 rounded-full bg-white"></span><span class="h-1 w-1/2 rounded-full bg-white/70"></span></span><span class="bg-linear-to-br from-zinc-400 to-zinc-600"></span></span>
            @break
        @case ('centered')
            <span class="flex flex-1 flex-col items-center justify-center gap-1 bg-brand-surface"><span class="size-2.5 rounded-full bg-brand"></span><span class="h-2 w-1/2 rounded-full bg-zinc-800"></span><span class="h-1.5 w-1/4 rounded-full bg-brand"></span></span>
            @break
        @case ('minimal')
            <span class="flex flex-1 items-end gap-2 p-2"><span class="flex flex-1 flex-col gap-1"><span class="h-3 w-full rounded-full bg-zinc-800"></span><span class="h-1 w-3/4 rounded-full bg-zinc-400"></span></span><span class="flex h-8 w-1/3 flex-col justify-end rounded-[4px] bg-white p-1"><span class="h-1.5 w-full rounded-full bg-brand"></span></span></span>
            @break
        @case ('image-card')
            <span class="m-2 flex flex-1 items-end overflow-hidden rounded-[4px] bg-linear-to-br from-zinc-400 to-zinc-600 p-1.5"><span class="flex w-1/2 flex-col gap-1 rounded-[3px] bg-white p-1"><span class="h-1.5 w-4/5 rounded-full bg-zinc-800"></span><span class="h-1 w-1/2 rounded-full bg-brand"></span></span></span>
            @break
    @endswitch
</span>
