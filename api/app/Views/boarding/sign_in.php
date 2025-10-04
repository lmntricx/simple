<main id="sign-in-main">
    <div id="sign-in-header" class="">
        <div id="back-arrow-container">
            <a href="/">BACK</a>
        </div>
        <h3>
            Welcome <br/>
            Back.
        </h3>
        <p>Continue your adventure</p>
    </div>
    <form name="" method="post" action="/sign-in" enctype="multipart/form-data" >

        <label>
            <input type="email" name="email" maxlength="150"  placeholder="example@email.com">
        </label>

        <label>
            <input type="password" name="password" maxlength="50" placeholder="PASSWORD">
        </label>
        <br/>

        <input id="rememberUser" type="checkbox" name="rememberUser" style="margin-left: 2px;margin-right: 2px" >
        <label for="rememberUser" style="display: unset">Remember me?</label>
        <br/><br/>

        <label>
            <input type="submit" value="Sign in">
        </label>
        <p>New here?  <a href="/sign-up"><b>Sign Up</b></a> </p>
    </form>
</main>
