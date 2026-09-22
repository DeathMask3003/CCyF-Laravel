<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#611232">
    <title>@yield('title', 'CCyF') · CoBaEMex</title>
    <style>
        :root { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #281923; background: #f7f4f1; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        .shell { min-height: 100vh; display: grid; grid-template-columns: minmax(280px, 44%) 1fr; }
        .brand { background: linear-gradient(155deg, #611232 0%, #431022 68%, #280b19 100%); color: #fff; padding: clamp(2rem, 6vw, 5rem); display: flex; flex-direction: column; justify-content: space-between; gap: 3rem; }
        .brand img { display: block; max-width: 240px; width: 70%; height: auto; }
        .brand h1 { font-family: Georgia, serif; font-size: clamp(2.5rem, 4vw, 4.2rem); font-weight: 500; line-height: 1.08; margin: 0; }
        .brand p { color: #ead6dd; font-size: 1.04rem; line-height: 1.6; max-width: 32rem; }
        .brand small { color: #d9bac5; }
        .content { padding: clamp(2rem, 7vw, 6rem); display: flex; align-items: center; justify-content: center; }
        .card { width: min(100%, 460px); background: #fff; border: 1px solid #e9e0e3; border-radius: 22px; box-shadow: 0 20px 65px rgba(54, 16, 34, .08); padding: clamp(1.7rem, 4vw, 3rem); }
        .eyebrow { color: #8b6b37; font-weight: 700; font-size: .74rem; text-transform: uppercase; letter-spacing: .14em; }
        h2 { font-family: Georgia, serif; font-size: 2rem; font-weight: 500; margin: .7rem 0 1rem; }
        .muted { color: #705f67; line-height: 1.55; }
        label { display: block; margin: 1.25rem 0 .4rem; font-weight: 650; font-size: .88rem; }
        input { display: block; width: 100%; border: 1px solid #cfbec5; border-radius: 11px; padding: .85rem 1rem; font: inherit; background: #fff; }
        input:focus { outline: 3px solid rgba(197, 165, 114, .35); border-color: #8c3552; }
        button { border: 0; border-radius: 11px; background: #611232; color: white; padding: .9rem 1.2rem; font: inherit; font-weight: 650; cursor: pointer; }
        button:hover { background: #4b0e26; }
        .primary { width: 100%; margin-top: 1.5rem; }
        .link-button { background: transparent; color: #611232; padding: .55rem 0; text-decoration: underline; }
        .link-button:hover { background: transparent; color: #3d0c1f; }
        .error { color: #9d1839; margin-top: .4rem; font-size: .86rem; }
        .notice { background: #f8f1df; border-radius: 10px; padding: .85rem 1rem; color: #5d4925; margin: 1rem 0; }
        @media (max-width: 800px) { .shell { grid-template-columns: 1fr; } .brand { min-height: 270px; padding: 2rem; } .brand h1 { font-size: 2.25rem; } .brand p { margin-bottom: 0; } .content { padding: 2rem 1rem; } }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="brand">
            <img src="{{ asset('images/ccyf.png') }}" alt="CCyF">
            <div>
                <h1>Concurso de Cafetería y Fotocopiado</h1>
                <p>Gestión de convocatorias, propuestas y evaluación para la comunidad de CoBaEMex.</p>
            </div>
            <small>CoBaEMex · CCyF</small>
        </aside>
        <main class="content">@yield('content')</main>
    </div>
</body>
</html>
