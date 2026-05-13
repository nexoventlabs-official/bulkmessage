@extends('layouts.app')
@section('title', 'Add WhatsApp Account')
@section('page-title', 'Add WhatsApp Account')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">WhatsApp Business Credentials</h3>
            <p class="text-sm text-gray-500 mt-1">Enter your Meta WhatsApp Business API credentials</p>
        </div>

        <form method="POST" action="{{ route('accounts.store') }}" class="p-6 space-y-5">
            @csrf

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-medium text-green-800 text-sm mb-3"><i class="fas fa-user mr-1"></i> Account Info</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm"
                               placeholder="e.g. My Business Account">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm"
                               placeholder="e.g. +91 98765 43210">
                        @error('mobile_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h4 class="font-medium text-purple-800 text-sm mb-3"><i class="fab fa-meta mr-1"></i> Meta App Credentials</h4>
                <p class="text-xs text-purple-600 mb-3">From Meta Developer Console &rarr; Your App &rarr; App Settings &rarr; Basic</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">App ID *</label>
                        <input type="text" name="meta_app_id" value="{{ old('meta_app_id') }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none text-sm font-mono"
                               placeholder="123456789012345">
                        @error('meta_app_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">App Secret *</label>
                        <input type="password" name="meta_app_secret" value="{{ old('meta_app_secret') }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none text-sm font-mono"
                               placeholder="abc123def456...">
                        @error('meta_app_secret') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-800 text-sm mb-3"><i class="fab fa-whatsapp mr-1"></i> WhatsApp API Details</h4>
                <p class="text-xs text-blue-600 mb-3">From Meta Developer Console &rarr; Your App &rarr; WhatsApp &rarr; API Setup</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Token *</label>
                        <textarea name="meta_access_token" required rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono"
                                  placeholder="EAABx...">{{ old('meta_access_token') }}</textarea>
                        <p class="text-[10px] text-gray-500 mt-1">Temporary or permanent token from WhatsApp &rarr; API Setup</p>
                        @error('meta_access_token') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID *</label>
                            <input type="text" name="meta_phone_number_id" value="{{ old('meta_phone_number_id') }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono"
                                   placeholder="123456789012345">
                            @error('meta_phone_number_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WABA ID * <span class="font-normal text-gray-400">(WhatsApp Business Account)</span></label>
                            <input type="text" name="meta_waba_id" value="{{ old('meta_waba_id') }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono"
                                   placeholder="123456789012345">
                            <p class="text-[10px] text-gray-500 mt-1">Used for template submission. Find in WhatsApp &rarr; API Setup</p>
                            @error('meta_waba_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Offer Template</label>
                        <input type="text" name="whatsapp_offer_template" value="{{ old('whatsapp_offer_template') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                               placeholder="Optional default template name">
                        @error('whatsapp_offer_template') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600">
                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                        <strong>Verify Token</strong> will be auto-generated. After adding the account, you'll get a <strong>Webhook Callback URL</strong> and <strong>Verify Token</strong> to configure in Meta.
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('accounts.index') }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition shadow-lg shadow-green-500/20">
                    <i class="fas fa-plus mr-1"></i> Add Account & Check Connection
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
