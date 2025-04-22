<main id="sign-up-main">
    <div id="sign-up-header" class="">
        <div id="back-arrow-container">
            <a href="/">BACK</a>
        </div>
        <h3>
            Create <br/>
            Account.
        </h3>
    </div>
    <form name="" method="post" action="/sign-up" enctype="multipart/form-data" >
        <label>
            <input type="text" name="referralCode" maxlength="100" accept="text/plain" placeholder="Promo Code" value="xhw78q">
        </label>
        <label>
            <input type="text" name="firstName" maxlength="100" accept="text/plain" placeholder="First Name">
        </label>
        <label>
            <input type="text" name="lastName" maxlength="100" accept="text/rtf" placeholder="Last Name">
        </label>
        <label>
            <input type="email" name="email" maxlength="150" accept="application/vnd.omads-email+xml" placeholder="example@email.com">
        </label>
        <label>
            <input type="number" name="phoneNumber" maxlength="50" accept="application/vnd.apple.numbers" placeholder="+2765 875 1452">
        </label>
        <label>
            <input type="password" name="password" maxlength="50" placeholder="PASSWORD">
        </label>
        <label>
            <input type="password" name="passwordVerify" maxlength="50" placeholder="CONFIRM PASSWORD">
        </label>
        <br/>

        <input id="terms-check" type="checkbox" name="terms" style="margin-left: 2px;margin-right: 2px" >
        <label for="terms-check" style="display: unset">Agree to terms and conditions</label>
        <br/><br/>

        <label>
            <input type="submit" value="Sign Up">
        </label>
        <p>Already have an account?  <a href="/sign-in"><b>Sign in</b></a> </p>
    </form>
</main>

