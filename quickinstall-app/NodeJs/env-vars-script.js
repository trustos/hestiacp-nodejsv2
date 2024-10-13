document.addEventListener("DOMContentLoaded", function () {
  console.log("NodeJs setup script loaded");
  console.log("Existing .env contents:", existingEnv);

  var form = document.querySelector("form");
  if (form) {
    // Populate form fields with existing .env values
    Object.keys(existingEnv).forEach(function (key) {
      var input = form.querySelector('[name="webapp_' + key + '"]');
      if (input) {
        input.value = existingEnv[key];
      }
    });

    // Create and append Environment Variables Section
    var envSection = document.createElement("div");
    envSection.className = "u-mb10";
    envSection.innerHTML = "<h3>Environment Variables</h3>";

    var envContainer = document.createElement("div");
    envContainer.id = "env-variables-container";

    Object.keys(existingEnv).forEach(function (key) {
      if (key.toLowerCase() === "port") {
        const portInput = form.querySelector('[name="webapp_' + key + '"]');
        portInput && portInput.setAttribute("value", existingEnv[key]);
      } else {
        appendEnvRow(envContainer, key, existingEnv[key]);
      }
    });

    // Add a button to add new environment variables
    var addButton = document.createElement("button");
    addButton.textContent = "Add Environment Variable";
    addButton.type = "button";
    addButton.className = "button";
    addButton.onclick = function () {
      appendEnvRow(envContainer, "", "");
    };

    envSection.appendChild(envContainer);
    envSection.appendChild(addButton);

    // Append the entire section to the form
    form.appendChild(envSection);

    form.addEventListener("submit", function (event) {
      // Gather all environment variables before submit
      // var envVars = {};
      // var rows = envContainer.querySelectorAll(".env-row");
      // rows.forEach(function (row) {
      //   var keyInput = row.querySelector(".env-key");
      //   var valueInput = row.querySelector(".env-value");
      //   if (keyInput.value) {
      //     envVars[keyInput.value] = valueInput.value;
      //   }
      // });

      // // Add a hidden input to the form with all env vars
      // var envInput = document.createElement("input");
      // envInput.type = "hidden";
      // envInput.name = "environment_variables";
      // envInput.value = JSON.stringify(envVars);
      // form.appendChild(envInput);

      console.log("Form submitted with env vars:", envVars);
    });
  } else {
    console.log("Form not found");
  }
});

function appendEnvRow(container, key, value) {
  var row = document.createElement("div");
  row.className = "env-row u-mb10";
  row.innerHTML = `
    <input type="text" class="form-control env-key" value="${key}" placeholder="Key" style="width: 45%; display: inline-block; margin-right: 5%;">
    <input type="text" class="form-control env-value" value="${value}" placeholder="Value" style="width: 45%; display: inline-block;">
    <button type="button" class="button button-danger" onclick="this.parentElement.remove()">Remove</button>
  `;
  container.appendChild(row);
}
