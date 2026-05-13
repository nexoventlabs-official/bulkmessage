@extends('layouts.app')
@section('title', 'Create Template')
@section('page-title', 'Create Template for ' . $account->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Form --}}
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Template Details</h3>
                    <p class="text-sm text-gray-500 mt-1">Create a message template with image/video + text content</p>
                </div>

                <form method="POST" action="{{ route('templates.store', $account->_id) }}" enctype="multipart/form-data" class="p-6 space-y-5" id="templateForm"
                      x-data="templateForm()">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Name *</label>
                            <input type="text" name="name" x-model="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                                   placeholder="e.g. Festival Greeting">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Language *</label>
                                <select name="language" x-model="language" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                                    <option value="en">English</option>
                                    <option value="ta">Tamil</option>
                                    <option value="hi">Hindi</option>
                                    <option value="te">Telugu</option>
                                    <option value="kn">Kannada</option>
                                    <option value="ml">Malayalam</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                                <select name="category" x-model="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                                    <option value="marketing">Marketing</option>
                                    <option value="utility">Utility</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Header --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Header Type</label>
                        <div class="flex gap-2">
                            <button type="button" @click="headerType = 'none'" :class="headerType === 'none' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition">None</button>
                            <button type="button" @click="headerType = 'image'" :class="headerType === 'image' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <i class="fas fa-image mr-1"></i>Image
                            </button>
                            <button type="button" @click="headerType = 'video'" :class="headerType === 'video' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                <i class="fas fa-video mr-1"></i>Video
                            </button>
                        </div>
                        <input type="hidden" name="header_type" :value="headerType">

                        <div x-show="headerType === 'image' || headerType === 'video'" class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload <span x-text="headerType === 'image' ? 'Image' : 'Video'"></span> *</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition cursor-pointer"
                                 @click="$refs.mediaInput.click()">
                                <template x-if="!mediaPreview">
                                    <div>
                                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 mb-2"></i>
                                        <p class="text-sm text-gray-500">Click to upload or drag & drop</p>
                                        <p class="text-xs text-gray-400 mt-1">Max 16MB</p>
                                    </div>
                                </template>
                                <template x-if="mediaPreview && headerType === 'image'">
                                    <img :src="mediaPreview" class="max-h-40 mx-auto rounded-lg">
                                </template>
                                <template x-if="mediaPreview && headerType === 'video'">
                                    <video :src="mediaPreview" class="max-h-40 mx-auto rounded-lg" controls></video>
                                </template>
                            </div>
                            <input type="file" name="header_media" x-ref="mediaInput" class="hidden"
                                   :accept="headerType === 'image' ? 'image/*' : 'video/*'"
                                   @change="handleMediaUpload($event)">
                        </div>
                    </div>

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message Body *</label>
                        <textarea name="body_text" x-model="bodyText" required rows="5"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                                  placeholder="Enter your message content here...&#10;&#10;You can use *bold*, _italic_, ~strikethrough~"
                                  maxlength="1024"></textarea>
                        <p class="text-xs text-gray-400 mt-1"><span x-text="bodyText.length"></span>/1024 characters</p>
                        @error('body_text') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Footer --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Footer <span class="text-gray-400">(optional)</span></label>
                        <input type="text" name="footer_text" x-model="footerText"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                               placeholder="e.g. Reply STOP to opt out" maxlength="60">
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('accounts.show', $account->_id) }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="px-5 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition shadow-lg shadow-blue-500/20">
                            <i class="fas fa-paper-plane mr-1"></i> Create & Submit to Meta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Live Preview --}}
        <div class="lg:col-span-2">
            <div class="sticky top-6">
                <h4 class="text-sm font-medium text-gray-600 mb-3">Live Preview</h4>
                <div class="bg-[#e5ddd5] rounded-2xl p-4" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;100&quot; height=&quot;100&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h100v100H0z&quot; fill=&quot;%23d4cdc4&quot; fill-opacity=&quot;.1&quot;/%3E%3C/svg%3E')">
                    <div class="bg-white rounded-xl shadow-sm max-w-xs mx-auto overflow-hidden" x-data="templateForm()">
                        {{-- Preview Header --}}
                        <div x-show="headerType !== 'none'" class="bg-gray-200 h-40 flex items-center justify-center">
                            <template x-if="mediaPreview && headerType === 'image'">
                                <img :src="mediaPreview" class="w-full h-40 object-cover">
                            </template>
                            <template x-if="!mediaPreview">
                                <div class="text-gray-400 text-center">
                                    <i :class="headerType === 'video' ? 'fas fa-video' : 'fas fa-image'" class="text-3xl"></i>
                                    <p class="text-xs mt-1" x-text="headerType === 'video' ? 'Video' : 'Image'"></p>
                                </div>
                            </template>
                        </div>
                        {{-- Preview Body --}}
                        <div class="p-3">
                            <p class="text-sm text-gray-800 whitespace-pre-wrap" x-text="bodyText || 'Your message will appear here...'"></p>
                            <p x-show="footerText" class="text-xs text-gray-400 mt-2" x-text="footerText"></p>
                            <p class="text-[10px] text-gray-400 text-right mt-1">{{ now()->format('h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function templateForm() {
    return {
        name: '',
        language: 'en',
        category: 'marketing',
        headerType: 'none',
        bodyText: '',
        footerText: '',
        mediaPreview: null,

        handleMediaUpload(event) {
            const file = event.target.files[0];
            if (file) {
                this.mediaPreview = URL.createObjectURL(file);
            }
        }
    }
}
</script>
@endpush
@endsection
