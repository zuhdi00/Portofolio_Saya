<?php
include '../template/header.php';
include '../template/sidebar.php';
?>

<main id="main" class="main">
    <div class="container">
        <h1 class="text-center my-4 welcome-title">Hey there! Welcome to Recruitment Management</h1>
        
        <div class="card">
            <a class="App-button nav-link active" id="lowongan-tab" href="index.php#lowongan" role="tab" aria-controls="lowongan" aria-selected="true">let's get started</a>
        </div>
    </div>
</main><!-- End #main -->

<style>
    body {
        background-color:rgb(251, 248, 248);
        color: #e0e0e0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        animation: fadeIn 1s ease-in-out;
    }
    .welcome-title {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #1e90ff;
        animation: fadeInDown 1s ease-in-out;
    }
    .welcome-text {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #87cefa;
        animation: fadeInUp 1s ease-in-out;
    }
    .nav-tabs .nav-link {
        border: 1px solid #1e90ff;
        border-radius: 0.25rem;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #1e90ff;
        transition: background-color 0.3s ease, color 0.3s ease;
        animation: fadeIn 1s ease-in-out;
    }
    .nav-tabs .nav-link.active {
        background-color: #1e90ff;
        color: white;
    }
    .nav-tabs .nav-link:hover {
        background-color: #87cefa;
        color: #1e90ff;
    }
    .card {
        background-color: #1e2127;
        border: 3px solid #1e90ff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 700;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 30px 20px;
        color: #1e90ff;
        border-radius: 45px;
        margin-top: 20px;
        animation: fadeIn 1s ease-in-out;
    }
    .App-logo {
        pointer-events: none;
        margin-top: -10px;
        animation: App-logo-spin infinite 5s linear;
    }
    .App-button {
        padding: 10px 20px;
        background-color: transparent;
        color: #1e90ff;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 15px;
        border: 3px solid #1e90ff;
        border-radius: 5em;
        margin-top: 20px;
        transition: 0.1s;
        text-decoration: none;
        text-align: center;
        animation: fadeIn 1s ease-in-out;
    }
    .App-button:hover {
        color: #1e2127;
        background-color: #1e90ff;
    }
    @media (prefers-reduced-motion: no-preference) {
        .App-logo {
            animation: App-logo-spin infinite 5s linear;
        }
    }
    @keyframes App-logo-spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
    @keyframes fadeIn {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }
    @keyframes fadeInDown {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
</style>

<?php
include '../template/footer.php';
?>