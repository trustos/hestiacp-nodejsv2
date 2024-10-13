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

    // Create collapsible header
    var envHeader = document.createElement("div");
    envHeader.innerHTML =
      '<h4 style="cursor: pointer;">Environment Variables ▼</h4>';
    envHeader.onclick = function () {
      envContainer.style.display =
        envContainer.style.display === "none" ? "block" : "none";
      this.querySelector("h4").innerHTML =
        "Environment Variables " +
        (envContainer.style.display === "none" ? "▼" : "▲");
    };
    envSection.appendChild(envHeader);

    var envContainer = document.createElement("div");
    envContainer.id = "env-variables-container";
    envContainer.style.display = "none"; // Initially collapsed

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
    form.querySelector(".form-container").appendChild(envSection);

    form.addEventListener("submit", function (event) {
      // Gather all environment variables
      var envVars = {};
      var rows = envContainer.querySelectorAll(".env-row");
      rows.forEach(function (row) {
        var keyInput = row.querySelector(".env-key");
        var valueInput = row.querySelector(".env-value");
        if (keyInput.value) {
          envVars[keyInput.value] = valueInput.value;
        }
      });

      // Create a hidden input field to store all env vars
      var hiddenInput = document.createElement("input");
      hiddenInput.type = "hidden";
      hiddenInput.name = "webapp_env_vars";
      hiddenInput.value = JSON.stringify(envVars);
      form.appendChild(hiddenInput);

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
    <input type="text" class="form-control env-key" value="${key}" placeholder="Key" style="width: 42%; display: inline-block; margin-right: 1%;">
    <input type="text" class="form-control env-value" value="${value}" placeholder="Value" style="width: 42%; display: inline-block;">
    <button type="button" class="button button-danger" onclick="this.parentElement.remove()" style="min-width: 1%;">Remove</button>
  `;
  container.insertBefore(row, container.lastChild);
}
