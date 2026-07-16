/**
 * -------------------------------------------------------------------------
 * opencitaseg plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of opencitaseg.
 *
 * opencitaseg is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * opencitaseg is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with opencitaseg. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2026 by opencitaseg plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/Open-SA/opencitaseg
 * -------------------------------------------------------------------------
 */

document.addEventListener("DOMContentLoaded", function () {
  // Use GLPI's native client-side translation helper. GLPI loads every
  // plugin's gettext catalogue into the global `i18n` object (see
  // FrontEndAssetsExtension::localesJs()), so `__(msgid, 'opencitaseg')`
  // resolves against this plugin's `.mo` files and honours the user's
  // locale. If `__` is somehow unavailable, fall back to the msgid itself.
  const DOMAIN = "opencitaseg";
  const t = (msgid) =>
    typeof window.__ === "function" ? window.__(msgid, DOMAIN) : msgid;

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
        boton.title = t("Quote this followup");
        boton.innerHTML = '<i class="ti ti-quote"></i> ' + t("Quote");

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

  // Polls until the given TinyMCE editor has finished its async init, then
  // calls onReady(editor). Calling editor.focus()/execCommand() before this
  // (e.g. right after Bootstrap starts opening the reply panel) can hit the
  // editor mid-initialization and throw, so this replaces a flat delay with
  // an actual readiness check.
  function esperarEditor(textareaId, onReady, onTimeout, intentosRestantes = 30) {
    const editor = typeof tinymce !== "undefined" ? tinymce.get(textareaId) : null;

    if (editor && editor.initialized) {
      onReady(editor);
      return;
    }

    if (intentosRestantes <= 0) {
      onTimeout();
      return;
    }

    setTimeout(
      () => esperarEditor(textareaId, onReady, onTimeout, intentosRestantes - 1),
      100,
    );
  }

  let citaOperacionEnCurso = false;

  document.body.addEventListener("click", function (e) {
    const enlaceNavegacion = e.target.closest('a[href^="#ITILFollowup_"]');
    if (enlaceNavegacion) {
      e.preventDefault();

      const targetId = enlaceNavegacion.getAttribute("href").substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        targetElement.scrollIntoView({ behavior: "smooth", block: "center" });

        const card = targetElement.querySelector(".card") || targetElement;
        // Transient highlight handled via a CSS class instead of inline styles.
        card.classList.add("opencitaseg-highlight");

        setTimeout(() => {
          card.classList.remove("opencitaseg-highlight");
        }, 1500);
      }
      return;
    }

    const botonCitar = e.target.closest(".btn-citar-seguimiento");
    if (!botonCitar) return;

    e.preventDefault();

    // Guard against a second click landing while a previous citation is
    // still being inserted (e.g. the user re-clicking after seeing the
    // panel take a moment to open) — without this, both clicks would each
    // insert their own copy of the quote.
    if (citaOperacionEnCurso) return;
    citaOperacionEnCurso = true;
    const liberarGuard = () => {
      citaOperacionEnCurso = false;
    };

    const idSeguimiento = botonCitar.getAttribute("data-id");

    const insertarCita = () => {
      const formularioRespuesta = document.querySelector(
        "#new-ITILFollowup-block form",
      );

      if (!formularioRespuesta) {
        liberarGuard();
        return;
      }

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
      let autorCita = t("User");

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

      // "Quoting %s" — format string kept translatable; %s is the author.
      const etiquetaCita = t("Quoting %s").replace("%s", autorCita);

      // NOTE: the inline styles below are intentional. This blockquote is
      // saved as part of the follow-up HTML content and is later rendered
      // in contexts where the plugin CSS is NOT loaded (mail notifications,
      // openpdf exports, etc.), so the styling must travel with the content.
      // The `opencitaseg-quote` class is added on top for timeline styling.
      const htmlCita = `
                    <blockquote contenteditable="false" class="mceNonEditable opencitaseg-quote" style="border-left: 3px solid #0078d4; padding-left: 10px; margin-left: 0; color: #555; background-color: #f8f9fa; padding: 10px; border-radius: 4px; user-select: none;">
                        <strong><a href="#ITILFollowup_${idSeguimiento}" class="opencitaseg-quote-link" style="text-decoration: none; color: #0078d4;">
                            <i class="ti ti-link"></i> ${etiquetaCita}
                        </a>:</strong><br>
                        ${textoCitado}
                    </blockquote>
                    <p>&nbsp;</p>
                `;

      const textarea = formularioRespuesta.querySelector(
        'textarea[name="content"]',
      );

      if (!textarea || typeof tinymce === "undefined") {
        liberarGuard();
        return;
      }

      esperarEditor(
        textarea.id,
        (editor) => {
          document
            .getElementById("new-itilobject-form")
            .scrollIntoView({ behavior: "smooth", block: "center" });

          editor.focus();
          editor.selection.select(editor.getBody(), true);
          editor.selection.collapse(false);
          editor.execCommand("mceInsertContent", false, htmlCita);
          editor.selection.collapse(false);

          liberarGuard();
        },
        () => {
          console.error("TinyMCE no reconoció el ID: " + textarea.id);
          liberarGuard();
        },
      );
    };

    const panelSeguimiento = document.getElementById("new-ITILFollowup-block");
    if (panelSeguimiento && !panelSeguimiento.classList.contains("show")) {
      const btnToggle = document.querySelector(
        '[data-bs-target="#new-ITILFollowup-block"]',
      );

      if (btnToggle) {
        // Wait for Bootstrap's own "finished opening" event instead of a
        // fixed delay — the collapse transition can take longer than any
        // flat timeout, and interacting with the editor mid-transition is
        // what caused the "not in standards mode" / getRng race before.
        panelSeguimiento.addEventListener("shown.bs.collapse", insertarCita, {
          once: true,
        });
        btnToggle.click();
      } else {
        panelSeguimiento.classList.add("show");
        insertarCita();
      }
    } else {
      insertarCita();
    }
  });
});
