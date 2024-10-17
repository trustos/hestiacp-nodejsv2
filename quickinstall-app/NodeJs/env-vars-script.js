document.addEventListener("DOMContentLoaded", function () {
  var form = document.querySelector("form");

  if (form) {
    // Create the warning message div
    const warningDiv = document.createElement("div");
    warningDiv.className = "alert alert-warning alert-dismissible u-mb10";
    warningDiv.role = "alert";
    warningDiv.style.cssText =
      "border-color: #ffeeba; background-color: #fff3cd; color: #856404; display: none;";
    warningDiv.innerHTML = `
          <div>
            <p class="u-mb10">Port in use Warning!</p>
            <p class="u-mb10">The port <span id="port-in-use"></span> you have chosen is already in use! Make sure that you point the app to the right one</p>
          </div>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        `;

    // Find the form-container and its h1
    const formContainer = form.querySelector(".form-container");
    const h1 = formContainer.querySelector("h1");

    // Insert the warning div after the h1
    if (h1 && h1.nextSibling) {
      formContainer.insertBefore(warningDiv, h1.nextSibling);
    } else {
      // If there's no h1 or it's the last element, append to the form-container
      formContainer.appendChild(warningDiv);
    }

    const portInputListener = form.querySelector('[name="webapp_port"]');

    console.log(appData);

    if (portInputListener) {
      portInputListener.addEventListener("input", (event) => {
        const enteredPort = event.target.value;

        // Check if the entered port is in the openPorts object
        if (appData.openPorts && typeof appData.openPorts === "object") {
          const isPortInUse = Object.values(appData.openPorts).includes(
            enteredPort,
          );
          if (isPortInUse) {
            document.getElementById("port-in-use").textContent = enteredPort;
            warningDiv.style.display = "block";
          } else {
            warningDiv.style.display = "none";
          }
        } else {
          console.error("appData.openPorts is not an object or is undefined");
          warningDiv.style.display = "none";
        }
      });
    }

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

    Object.keys(appData).forEach(function (key) {
      if (key === "node_version" && appData[key]) {
        const nodeVersionSelect = form.querySelector(
          '[name="webapp_' + key + '"]',
        );
        if (nodeVersionSelect) {
          nodeVersionSelect.value = appData[key];
        }
      } else if (key === "start_script") {
        const startScriptInput = form.querySelector(
          '[name="webapp_' + key + '"]',
        );
        startScriptInput &&
          startScriptInput.setAttribute("value", appData[key]);
      } else if (key.toLowerCase() === "port") {
        const portInput = form.querySelector(
          '[name="webapp_' + key.toLowerCase() + '"]',
        );
        portInput && portInput.setAttribute("value", appData[key]);
      } else if (key === "modules_type") {
        const modulesTypeSelect = form.querySelector(
          '[name="webapp_' + key + '"]',
        );
        if (modulesTypeSelect) {
          modulesTypeSelect.value = appData[key];
        }
      } else if (key === "openPorts") {
        // Do not process openPorts
      } else {
        appendEnvRow(envContainer, key, appData[key]);
      }
    });

    // Add a button to add new environment variables
    var addButton = document.createElement("button");
    addButton.type = "button";
    addButton.className = "button";

    // Create the icon element
    var icon = document.createElement("i");
    icon.className = "fas fa-plus icon-green";

    // Create a text node for the button text
    var buttonText = document.createTextNode(" Add Environment Variable");

    // Append the icon and text to the button
    addButton.appendChild(icon);
    addButton.appendChild(buttonText);

    addButton.onclick = function () {
      if (envContainer.style.display === "none") {
        envContainer.style.display = "block";
        envHeader.querySelector("h4").innerHTML = "Environment Variables ▲";
      }
      appendEnvRow(envContainer, "", "");
    };

    envSection.appendChild(envContainer);
    envSection.appendChild(addButton);

    // Append the entire section to the form
    form.querySelector(".form-container").appendChild(envSection);

    form.addEventListener("submit", function (event) {
      event.preventDefault();

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
      hiddenInput.type = "text";
      hiddenInput.name = "webapp_env_vars";
      hiddenInput.value = JSON.stringify(envVars);
      hiddenInput.style.display = "none";
      form.appendChild(hiddenInput);
    });
  } else {
    console.error("Form not found");
  }
});

function appendEnvRow(container, key, value) {
  var row = document.createElement("div");
  row.className = "env-row u-mb10";
  row.innerHTML = `
    <input type="text" class="form-control env-key" value="${key}" placeholder="Key" style="width: 40%; display: inline-block; margin-right: 1%;">
    <input type="text" class="form-control env-value" value="${value}" placeholder="Value" style="width: 40%; display: inline-block;">
    <button type="button" class="button button-danger" onclick="this.parentElement.remove()" style="min-width: 1%;">
			<i class="fas fa-trash icon-red"></i>
      Del
    </button>
  `;
  container.appendChild(row);
}
