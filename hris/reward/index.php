<?php
include '../template/header.php';
include '../template/sidebar.php';
?>
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Reward</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active">Reward</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <form class="row g-3 needs-validation" novalidate>
                <div class="col-12">
                    <label for="yourName" class="form-label">Your Name</label>
                    <input type="text" name="name" class="form-control" id="yourName" required>
                    <div class="invalid-feedback">Please, enter your name!</div>
                </div>

                <div class="col-12">
                    <label for="yourEmail" class="form-label">Your Email</label>
                    <input type="email" name="email" class="form-control" id="yourEmail" required>
                    <div class="invalid-feedback">Please enter a valid Email adddress!</div>
                </div>

                <div class="col-12">
                    <label for="yourUsername" class="form-label">Username</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text" id="inputGroupPrepend">@</span>
                        <input type="text" name="username" class="form-control" id="yourUsername" required>
                        <div class="invalid-feedback">Please choose a username.</div>
                    </div>
                </div>

                <div class="col-12">
                  
                    <label for="alamat">Alamat</label>
<select id="alamat" name="alamat">
  <option value="Kalimantan Timur">Kalimantan Timur</option>
  <option value="Bandung">Bandung</option>
  <option value="Jakarta">Jakarta</option>
  </select>
 
 
                </div>
                <div>
                    <button type="button" class="btn btn-success w-100">Success</button>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" name="terms" type="checkbox" value="" id="acceptTerms" required>
                        <label class="form-check-label" for="acceptTerms">I agree and accept the <a href="#">terms and conditions</a></label>
                        <div class="invalid-feedback">You must agree before submitting.</div>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary w-100" type="submit">Create Account</button>
                </div>
                <div class="col-12">
                    <p class="small mb-0">Already have an account? <a href="pages-login.html">Log in</a></p>
                </div>
            </form>


        </div>
    </section>

</main><!-- End #main -->

<?php
include '../template/footer.php';
?>