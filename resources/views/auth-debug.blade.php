<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Debug Login</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        form { margin: 20px 0; }
        input { width: 100%; padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>Debug Login</h1>
    <p>Current auth status: {{ auth()->check() ? 'Authenticated as ' . auth()->user()->email : 'Not authenticated' }}</p>
    
    @if(auth()->check())
        <p>User verified: {{ auth()->user()->email_verified_at ? 'Yes' : 'No' }}</p>
        <a href="/dashboard" style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Dashboard</a>
        <br><br>
        <form action="/logout" method="POST">
            @csrf
            <button type="submit">Logout</button>
        </form>
    @else
        <form action="/debug-login" method="POST">
            @csrf
            <label>Email:</label>
            <input type="email" name="email" value="hellavion@gmail.com" required>
            
            <label>Password:</label>
            <input type="password" name="password" value="avi197350" required>
            
            <br><br>
            <button type="submit">Login</button>
        </form>
    @endif
</body>
</html>