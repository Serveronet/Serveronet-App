@extends('layouts.app', ['banner' => 'Admin Dashboard', 'help_tag' => \App\Dicts\DocsMapping::client_administration])

@section('content')
    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">
        <br>
        <table class="table table-sm table-striped">
            <tbody class="">
                @foreach ($indicators as $key => $indicator)
                    <tr>
                        <th class="overflow-hidden">
                            <b>{{ $key }}</b>
                        </th>
                        <td class="overflow-auto">
                            {{ $indicator }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
