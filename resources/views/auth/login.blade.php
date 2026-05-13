<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bulk WhatsApp Campaign</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-green-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-green-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-green-500/30">
                <i class="fab fa-whatsapp text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">Bulk Campaign</h1>
            <p class="text-gray-400 mt-1">Super Admin Panel</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Sign in to your account</h2>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                    @foreach($errors->all() as $error)
                        <p class="text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-1"></i>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                                   placeholder="admin@bulkcampaign.com">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" required
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                                   placeholder="Enter your password">
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300 text-green-500 focus:ring-green-500">
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>
                    <button type="submit"
                            class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2.5 rounded-lg transition shadow-lg shadow-green-500/30">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
