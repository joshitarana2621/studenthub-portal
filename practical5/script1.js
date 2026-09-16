
const registerForm = document.getElementById("registerForm");
const fullNameInput = document.getElementById("fullname");
const enrollmentInput = document.getElementById("enrollment");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirm_password");

const nameError = document.getElementById("nameError");
const enrollmentError = document.getElementById("enrollmentError");
const emailError = document.getElementById("emailError");
const passwordError = document.getElementById("passwordError");
const confirmPasswordError = document.getElementById("confirmPasswordError");

if (registerForm) {
    registerForm.addEventListener("submit", function (event) {
        event.preventDefault();

        const fullNamePattern = /^[A-Za-z\s]+$/;
        const enrollmentPattern = /^[A-Za-z0-9]+$/;
        const emailPattern = /^[a-z0-9]+@[a-z0-9]+\.[a-z]{2,}$/i;
        const passwordPattern = /^.{6,12}$/;

        let isValid = true;

        if (!fullNamePattern.test(fullNameInput.value.trim())) {
            nameError.style.color = "red";
            nameError.textContent = "Invalid full name!";
            isValid = false;
        } else {
            nameError.textContent = "";
        }

        if (!enrollmentPattern.test(enrollmentInput.value.trim()) || enrollmentInput.value.trim().length < 4) {
            enrollmentError.style.color = "red";
            enrollmentError.textContent = "Invalid enrollment number!";
            isValid = false;
        } else {
            enrollmentError.textContent = "";
        }

        if (!emailPattern.test(emailInput.value.trim())) {
            emailError.style.color = "red";
            emailError.textContent = "Invalid email!";
            isValid = false;
        } else {
            emailError.textContent = "";
        }

        if (!passwordPattern.test(passwordInput.value)) {
            passwordError.style.color = "red";
            passwordError.textContent = "Password must be at least 6 characters!";
            isValid = false;
        } else {
            passwordError.textContent = "";
        }

        if (confirmPasswordInput.value !== passwordInput.value) {
            confirmPasswordError.style.color = "red";
            confirmPasswordError.textContent = "Passwords do not match!";
            isValid = false;
        } else {
            confirmPasswordError.textContent = "";
        }

        if (!isValid) {
            return false;
        }

        alert("Registration successful!");
        window.location.href = "login.html";
    });
}
