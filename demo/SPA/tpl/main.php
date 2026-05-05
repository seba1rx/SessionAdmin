<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SessionAdmin — SPA Demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Tab indexation is disabled in this demo (useTabIndexation = false in config/session.php) -->
</head>
<body class="bg-light">
<div class="container py-4">

    <div class="p-4 mb-4 bg-primary text-white rounded">
        <h4 class="mb-1">SessionAdmin — SPA Demo</h4>
        <p class="mb-0 small text-white-50">Single-Page Application · No tab indexation · URL authorization enabled</p>
    </div>

    <div class="row g-4">

        <div class="col-md-5">
            <h6 class="text-muted text-uppercase small mb-3">Routes</h6>
            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item ps-0"><a href="#" onclick="App.redirect('/')">&#8635; Start</a></li>
                <li class="list-group-item ps-0"><a href="#" onclick="App.request.post({url:'/hello'})">Hello</a></li>
                <li class="list-group-item ps-0"><a href="#" onclick="App.request.post({url:'/demoData'})">Demo data</a></li>
                <li class="list-group-item ps-0"><a href="#" onclick="App.request.post({url:'/showLogin'})">Show login form</a></li>
                <?php if ($_SESSION['sessionadmin']['isUser'] ?? false): ?>
                <li class="list-group-item ps-0 fw-semibold">
                    <a href="#" onclick="App.addVar()">Add var to session</a>
                </li>
                <?php endif; ?>
            </ul>

            <?php if ($_SESSION['sessionadmin']['isUser'] ?? false): ?>
                <button class="btn btn-outline-danger btn-sm"
                        onclick="App.request.post({url:'/logout'})">Log out</button>
            <?php endif; ?>
        </div>

        <div class="col-md-7">
            <h6 class="text-muted text-uppercase small mb-3">Session contents</h6>
            <pre id="session_data" class="bg-white border rounded p-3 small"><?= json_encode($_SESSION, JSON_PRETTY_PRINT) ?></pre>
        </div>

    </div>

    <div id="content" class="mt-3"></div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
    const App = {
        getData: (formId) => {
            const form = document.getElementById(formId);
            if (!form) { console.error(`Form "${formId}" not found`); return {}; }
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(el => {
                if (!el.name) return;
                if (el.type === 'checkbox') data[el.name] = el.checked;
                else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
                else data[el.name] = el.value;
            });
            return data;
        },

        request: {
            get:    (args) => App.send('GET',    args),
            post:   (args) => App.send('POST',   args),
            put:    (args) => App.send('PUT',    args),
            delete: (args) => App.send('DELETE', args),
        },

        send: async (method, args) => {
            try {
                const opts = {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-Fetch-Request': 'true' },
                };
                if (args.data) opts.body = JSON.stringify(args.data);

                const res = await fetch(args.url, opts);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const ct = res.headers.get('content-type') ?? '';
                App.process(ct.includes('application/json') ? await res.json() : await res.text());
            } catch (e) {
                App.process({ error: e.message });
            }
        },

        process: (response) => {
            if (response.error)  Swal.fire({ icon: 'error', text: response.error });
            if (response.auth) {
                response.auth.ok
                    ? Swal.fire({ icon: 'success', title: 'Logged in!', html: response.auth.msg, confirmButtonText: 'Great!' })
                          .then(r => r.isConfirmed && window.location.reload())
                    : Swal.fire({ html: response.auth.msg });
            }
            if (response.html) {
                const el = document.getElementById(response.html.id);
                if (el) {
                    el.innerHTML = response.html.content;
                    el.querySelectorAll('script').forEach(old => {
                        const s = document.createElement('script');
                        [...old.attributes].forEach(a => s.setAttribute(a.name, a.value));
                        if (!old.src) s.text = old.textContent;
                        document.body.appendChild(s).remove();
                    });
                }
            }
            if (response.dialog) Swal.fire({ title: response.dialog.title ?? null, html: response.dialog.html ?? null });
            if (response.eval)   eval(response.eval);
        },

        redirect: (url) => window.location.replace(url),

        addVar: async () => {
            const { value } = await Swal.fire({
                title: 'Add to session',
                html: `<input id="sa_varname" class="swal2-input" placeholder="key">
                       <input id="sa_varvalue" class="swal2-input" placeholder="value">`,
                focusConfirm: false,
                preConfirm: () => ({
                    varname: document.getElementById('sa_varname').value,
                    value:   document.getElementById('sa_varvalue').value,
                }),
            });
            if (value) App.request.post({ url: '/addVarToSession', data: value });
        },
    };
</script>
</body>
</html>
