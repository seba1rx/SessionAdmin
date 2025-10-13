<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="assets/seba1rx_sessionAdmin.js"></script>
</head>
<body>
    <div class="wrapper p-5">
        <div class="row mt-5 justify-content-center">
            <div class="col-lg-8 col-sm-10 bg-info text-center text-white">
                <h3>This is a SPA (Single Page Application) app</h3>
                <br>
                <h3>Check the routes in the left and the session content in the right</h3>

                <?php if($_SESSION['isUser'] ?? false){ ?>
                <img src="assets/<?php echo $_SESSION['data']['avatar']; ?>" alt="avatar" style="max-width: 100px;" class="img-thumbnail mb-1 bg-info">
                <?php } ?>

            </div>
        </div>
        <div class="row justify-content-center no-gutters">
            <div class="col-lg-8 col-sm-10">
                <div class="row mt-5">
                    <div class="col-6">
                        <p>Click on each route to check this app working</p>
                        <dd>
                            <li style="cursor: pointer;"><span onclick="App.redirect('/')">Start</span></li>
                            <li style="cursor: pointer;"><span onclick="App.request.post({url:'/hello'})">Hello</span></li>
                            <li style="cursor: pointer;"><span onclick="App.request.post({url:'/demoData'})">Demo data</span></li>
                            <li style="cursor: pointer;"><span onclick="App.request.post({url:'/showLogin'})">Show login form</span></li>
                            <li id="addvar" class="d-none" style="cursor: pointer;"><span onclick="App.addVar()">Show form to add var to session</span></li>
                        </dd>

                        <br>

                        <?php if($_SESSION['isUser'] ?? false){ ?>
                            <button type="button" onclick="App.request.post({url:'/logout'})" class="btn btn-success mt-2">Log out</a>
                        <?php } ?>

                    </div>
                    <div class="col-6">
                        <h5>your session data:</h5>
                        <pre id="session_data"><?php echo json_encode($_SESSION ?? [], JSON_PRETTY_PRINT) ?></pre>
                    </div>
                </div>
            </div>
        </div>
        <div id="content" class="row justify-content-center no-gutters">
            <!-- dynamic content -->
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const App = {
            /**
             * Get all the form values in an indexed array
             *
             * usage: var data = App.getData('form_id');
             *
             * @param {String} form_id
             * @returns {object} - Objeto con pares { id: valor }
             */
            getData: (form_id) => {
                const form = document.getElementById(form_id);
                if (!form) {
                    console.error(`Form id "${form_id}" not found`);
                    return {};
                }

                const data = {};

                // select all items with attribute "name" in the form
                form.querySelectorAll('input, select, textarea').forEach(el => {
                    if (!el.name) return; // ignore those without name

                    if (el.type === 'checkbox') {
                        data[el.name] = el.checked;
                    } else if (el.type === 'radio') {
                        // just keep selected
                        if (el.checked) data[el.name] = el.value;
                    } else {
                        data[el.name] = el.value;
                    }
                });

                // console.log(data);
                return data;
            },

            request: {
                get: (args)=>{App.send('GET',args);},
                post: (args)=>{App.send('POST',args);},
                put: (args)=>{App.send('PUT',args);},
                delete: (args)=>{App.send('DELETE',args);}
            },

            /**
             * Sends the request
             * @param {string} method
             * @param {object} args
             * @param {string} args.url
             * @param {object} [args.data]
             */
            send: async (method, args = {url, data}) => {
                try {
                    const options = {
                        method,
                        headers: {
                            "Content-Type": "application/json",
                            "X-Fetch-Request": "true"
                        }
                    };

                    if (args.data) {
                        options.body = JSON.stringify(args.data);
                    }

                    const response = await fetch(args.url, options);

                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }

                    let result;
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.includes("application/json")) {
                        result = await response.json();
                    } else {
                        result = await response.text();
                    }

                    App.process(result);
                } catch (error) {
                    console.log("Error on request:", error.message);
                    App.process({ error: error.message });
                }
            },
            /**
             * Process the response
             * @param {object} response
             * @param {string} [response.error]
             * @param {object} [response.html]
             * @param {string} [response.html.id]
             * @param {string} [response.html.content]
             * @param {string} [response.dialog]
             */
            process: (response) => {
                if(response.error){
                    swal.fire({icon: "error", text: response.error});
                }
                if(response.auth){
                    if(response.auth.ok){
                        swal.fire({
                            icon: "success",
                            title: "Logged in!",
                            html: response.auth.msg,
                            confirmButtonText: "Hooray!",
                        }).then((result) => {
                            App.request.get({url:'/'}); // reloads te main view
                        });

                    }else{
                        swal.fire({html: response.auth.msg});
                    }
                }
                if(response.html){
                    container = document.getElementById(response.html.id);
                    container.innerHTML = response.html.content;
                    const scripts = container.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement('script');
                        for (const attr of oldScript.attributes) {
                            newScript.setAttribute(attr.name, attr.value);
                        }
                        if (!oldScript.src) {
                            newScript.text = oldScript.textContent;
                        }
                        document.body.appendChild(newScript);
                        newScript.remove();
                    });
                }
                if(response.dialog){
                    let title = null;
                    let html = null;
                    if(response.dialog.title) title = response.dialog.title;
                    if(response.dialog.html) html = response.dialog.html;

                    swal.fire({title: title, html: html});
                }
                if(response.eval){
                    eval(response.eval);
                }
            },
            redirect: (url) => {
                try{
                    window.location.replace(url);
                }catch(e){
                    swal.fire({icon:"error", title: e.message, html: e.stack});
                }
            },
            addVar: async () => {
                const { value: formValues } = await Swal.fire({
                    title: "Multiple inputs",
                    html: `
                        var name: <input id="varname" class="swal2-input">
                        var value: <input id="varvalue" class="swal2-input">
                    `,
                    focusConfirm: false,
                    preConfirm: () => {
                        return {
                            varname: document.getElementById("varname").value,
                            value: document.getElementById("varvalue").value
                        };
                    }
                });
                if (formValues) {
                    App.request.post({url:'/addVarToSession', data: formValues});
                }
            },
        };
    </script>
</body>
</html>