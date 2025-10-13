<div class="col-8 col-sm-10">
    <form id="loginForm" name="loginForm" class="form-inline mt-2">
        <input type="email" title="Enter email" class="form-control mr-1" name="useremail" placeholder="email@example.com" value="demo@mail.com">
        <input type="password" title="Enter password" class="form-control mr-1" name="userpassword" value="your_password_here">
        <button type="button" class="btn btn-info" onclick="tryToAuthenticate()">Authenticate</button>
    </form>
</div>

<script>
    function tryToAuthenticate(){
        var data = App.getData('loginForm');
        App.request.post({url: "/authenticate",  data: data});
    };
</script>