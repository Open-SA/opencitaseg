document.addEventListener("DOMContentLoaded", function () {
  function inyectarBotones() {
    const seguimientos = document.querySelectorAll(
      '.timeline-item[data-itemtype="ITILFollowup"]',
    );

    seguimientos.forEach((item) => {
      if (item.querySelector(".btn-citar-seguimiento")) return;

      const idSeguimiento = item.getAttribute("data-items-id");
      if (!idSeguimiento) return;

      const contenedorAcciones = item.querySelector(".timeline-item-buttons");

      if (contenedorAcciones) {
        const boton = document.createElement("a");
        boton.href = "#";
        boton.className =
          "btn btn-sm btn-ghost-secondary btn-citar-seguimiento me-2";
        boton.setAttribute("data-id", idSeguimiento);
        boton.title = "Citar este seguimiento";
        boton.innerHTML = '<i class="ti ti-quote"></i> Citar';

        contenedorAcciones.insertBefore(boton, contenedorAcciones.firstChild);
      }
    });
  }

  inyectarBotones();

  const observer = new MutationObserver(function (mutations) {
    let deberiamosInyectar = false;
    mutations.forEach(function (mutation) {
      if (mutation.addedNodes.length > 0) deberiamosInyectar = true;
    });
    if (deberiamosInyectar) inyectarBotones();
  });

  observer.observe(document.body, { childList: true, subtree: true });

  document.body.addEventListener("click", function (e) {
    const enlaceNavegacion = e.target.closest('a[href^="#ITILFollowup_"]');
    if (enlaceNavegacion) {
      e.preventDefault();

      const targetId = enlaceNavegacion.getAttribute("href").substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        targetElement.scrollIntoView({ behavior: "smooth", block: "center" });

        const card = targetElement.querySelector(".card") || targetElement;
        const colorOriginal = card.style.backgroundColor;

        card.style.transition = "background-color 0.4s ease";
        card.style.backgroundColor = "#fff3cd";

        setTimeout(() => {
          card.style.backgroundColor = colorOriginal;
        }, 1500);
      }
      return;
    }

    const botonCitar = e.target.closest(".btn-citar-seguimiento");
    if (!botonCitar) return;

    e.preventDefault();
    const idSeguimiento = botonCitar.getAttribute("data-id");

    const panelSeguimiento = document.getElementById("new-ITILFollowup-block");
    if (panelSeguimiento && !panelSeguimiento.classList.contains("show")) {
      const btnToggle = document.querySelector(
        '[data-bs-target="#new-ITILFollowup-block"]',
      );
      if (btnToggle) {
        btnToggle.click();
      } else {
        panelSeguimiento.classList.add("show");
      }
    }

    setTimeout(() => {
      const formularioRespuesta = document.querySelector(
        "#new-ITILFollowup-block form",
      );

      if (formularioRespuesta) {
        let inputOculto = document.getElementById("_quoted_followup_id");
        if (!inputOculto) {
          inputOculto = document.createElement("input");
          inputOculto.type = "hidden";
          inputOculto.id = "_quoted_followup_id";
          inputOculto.name = "_quoted_followup_id";
          formularioRespuesta.appendChild(inputOculto);
        }
        inputOculto.value = idSeguimiento;

        const elementoSeguimiento = document.querySelector(
          `#ITILFollowup_${idSeguimiento}`,
        );
        let textoCitado = "...";
        let autorCita = "Usuario";

        if (elementoSeguimiento) {
          const nodoTexto = elementoSeguimiento.querySelector(
            ".read-only-content .rich_text_container",
          );
          if (nodoTexto) textoCitado = nodoTexto.innerHTML;

          const autorNodo = elementoSeguimiento.querySelector(
            '.creator span[id^="user_"] a, .creator a[href*="user.form.php"]',
          );
          if (autorNodo && autorNodo.textContent.trim() !== "") {
            autorCita = autorNodo.textContent.trim();
          }
        }
        const htmlCita = `
                    <blockquote contenteditable="false" class="mceNonEditable" style="border-left: 3px solid #0078d4; padding-left: 10px; margin-left: 0; color: #555; background-color: #f8f9fa; padding: 10px; border-radius: 4px; user-select: none;">
                        <strong><a href="#ITILFollowup_${idSeguimiento}" style="text-decoration: none; color: #0078d4;">
                            <i class="ti ti-link"></i> Citando a ${autorCita}
                        </a>:</strong><br>
                        ${textoCitado}
                    </blockquote>
                    <p>&nbsp;</p>
                `;

        const textarea = formularioRespuesta.querySelector(
          'textarea[name="content"]',
        );

        if (textarea && typeof tinymce !== "undefined") {
          const editor = tinymce.get(textarea.id);

          if (editor) {
            document
              .getElementById("new-itilobject-form")
              .scrollIntoView({ behavior: "smooth", block: "center" });

            editor.focus();

            editor.selection.select(editor.getBody(), true);
            editor.selection.collapse(false);

            editor.execCommand("mceInsertContent", false, htmlCita);

            editor.selection.collapse(false);
          } else {
            console.error("TinyMCE no reconoció el ID: " + textarea.id);
          }
        }
      }
    }, 150);
  });
});
