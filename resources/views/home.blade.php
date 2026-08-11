@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-center">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 font-semibold text-gray-800">{{ __('Dashboard') }}</div>

                <div class="p-6 text-sm text-gray-600">
                    @if (session('status'))
                        <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-md mb-4 text-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ __('You are logged in!') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
