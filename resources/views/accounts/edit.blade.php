@extends('layouts.app')
@section('title', 'Edit Account')
@section('page-title', 'Edit: ' . $account->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Edit WhatsApp Credentials</h3>
        </div>
        <form method="POST" action="{{ route('accounts.update', $account->_id) }}" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-medium text-green-800 text-sm mb-3"><i class="fas fa-user mr-1"></i> Account Info</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                        <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $account->mobile_number) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm">
                        @error('mobile_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h4 class="font-medium text-purple-800 text-sm mb-3"><i class="fab fa-meta mr-1"></i> Meta App Credentials</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">App ID *</label>
                        <input type="text" name="meta_app_id" value="{{ old('meta_app_id', $account->meta_app_id) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none text-sm font-mono">
                        @error('meta_app_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">App Secret <span class="text-gray-400">(blank = keep current)</span></label>
                        <input type="password" name="meta_app_secret"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none text-sm font-mono"
                               placeholder="Leave blank to keep current">
                        @error('meta_app_secret') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-800 text-sm mb-3"><i class="fab fa-whatsapp mr-1"></i> WhatsApp API Details</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Token <span class="text-gray-400">(blank = keep current)</span></label>
                        <textarea name="meta_access_token" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono"
                                  placeholder="Leave blank to keep current token"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID *</label>
                            <input type="text" name="meta_phone_number_id" value="{{ old('meta_phone_number_id', $account->meta_phone_number_id) }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono">
                            @error('meta_phone_number_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WABA ID *</label>
                            <input type="text" name="meta_waba_id" value="{{ old('meta_waba_id', $account->meta_waba_id) }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm font-mono">
                            @error('meta_waba_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Offer Template</label>
                        <input type="text" name="whatsapp_offer_template" value="{{ old('whatsapp_offer_template', $account->whatsapp_offer_template) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                        @error('whatsapp_offer_template') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('accounts.show', $account->_id) }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition">
                    <i class="fas fa-save mr-1"></i> Update Account
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
