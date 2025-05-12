/**
 * The below code is for testing purpose on frontend
 * how we can extract text by using querySelector and innerHTML text
 *  
 */
document.body.innerHTML.includes("Your Error Message Here") // return true if your message is exists in dom

if (document.body.innerHTML.includes("Your Error Message Here")) {
    console.log("Error message found in page source.");
} else {
    console.log("Error message not found.");
}


// This method find you message by using querySelector
let errorMessage = document.querySelector("Your Dom Element"); //i.e span.loginErrorMessage
if (errorMessage && errorMessage.innerText.trim() !== "") {
    console.log("Element has text:", errorMessage.innerText);
} else {
    console.log("Element is empty or not found.");
}