@if (count($toc) > 0)
    <ul class="space-y-2">
        @foreach ($toc as $item)
            <li
                class="@if ($item['level'] == 2) before:content-['#']  font-medium text-gray-800 dark:text-gray-200 @else before:content-['##'] @endif before:text-[#00aaa6] @if ($item['level'] == 3) ml-4 @endif">
                <a
                    href="{{ route('documentation', ['doc_tag' => $doc_tag]) }}#{{ 'content-' . $item['anchor'] }}">{{ $item['title'] }}</a>
            </li>
        @endforeach
    </ul>
@endif
