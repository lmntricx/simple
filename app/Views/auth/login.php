<div id="error-message">
    <?php echo($_GET['error']); ?>
</div>
<br/>
<form action="/login" method="POST">

    <label for="email">Email:</label>

    <input type="email" name="email" id="email" required>
    <label for="password">Password:</label>
    <input type="password" name="password" id="password" required>
    <button type="submit">Login</button>
</form>
