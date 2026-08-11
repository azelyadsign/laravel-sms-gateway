@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-center">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 font-semibold text-gray-800">{{ __('Verify Your Email Address') }}</div>

                <div class="p-6 text-sm text-gray-600">
                    @if (session('resent'))
                        <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-md mb-4 text-sm">
                            {{ __('A fresh verification link has been sent to your email address.') }}
                        </div>
                    @endif

                    <p>{{ __('Before proceeding, please check your email for a verification link.') }}</p>
                    <p class="mt-2">
                        {{ __('If you did not receive the email') }},
                        <form class="inline" method="POST" action="{{ route('verification.resend') }}">
                            @csrf
                            <button type="submit" class="text-blue-600 hover:text-blue-800 underline bg-transparent border-0 p-0 cursor-pointer text-sm">{{ __('click here to request another') }}</button>.
                        </form>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
