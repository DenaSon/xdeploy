@if(!empty($items))

    <div class="breadcrumbs text-sm">

        <ul>

            @foreach($items as $item)

                <li>

                    @if(isset($item['url']))

                        <a
                            href="{{ $item['url'] }}"
                            wire:navigate
                        >
                            {{ $item['label'] }}
                        </a>

                    @else

                        {{ $item['label'] }}

                    @endif

                </li>

            @endforeach

        </ul>

    </div>

@endif
