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
  const t = (msgid) => (window.OPENCITASEG_I18N || {})[msgid] || msgid;

  const TIPOS_CITABLES = [
    "ITILFollowup",
    "TicketTask",
    "ChangeTask",
    "ProblemTask",
  ];

  const SELECTOR_CITABLES = TIPOS_CITABLES.map(
    (tipo) => `.timeline-item[data-itemtype="${tipo}"]`,
  ).join(", ");

  let citasHabilitadas = null;
  let citaPrivadaPorDefecto = false;

  function contextoItil() {
    const form = document.querySelector("#new-ITILFollowup-block form");
    if (form) {
      const itemtype = form.querySelector('input[name="itemtype"]')?.value;
      const itemsId = form.querySelector('input[name="items_id"]')?.value;
      if (itemtype && itemsId) return { itemtype, itemsId };
    }

    // Desde GLPI 11.0.9 el formulario de respuesta no esta en el DOM hasta que
    // el usuario abre el panel, asi que no sirve como fuente del contexto. El
    // formulario principal del objeto ITIL si esta, y lleva los mismos hidden.
    const principal = document.getElementById("new-itilobject-form");
    if (!principal) return null;

    const itemtype = principal.querySelector('input[name="itemtype"]')?.value;
    const itemsId = principal.querySelector('input[name="items_id"]')?.value;
    if (!itemtype || !itemsId) return null;

    return { itemtype, itemsId };
  }

  // La ruta base se deriva del src de este mismo script: GLPI instala los
  // plugins del marketplace en marketplace/ y no en plugins/, asi que
  // hardcodear /plugins/ rompe esa instalacion.
  function rutaBasePlugin() {
    const script = document.querySelector(
      'script[src*="opencitaseg"][src*="citas.js"]',
    );
    if (!script) return null;

    return script.src.replace(/\/(public\/)?js\/citas\.js.*$/, "");
  }

  // Citas de versiones anteriores del plugin, que no llevaban la clase
  // opencitaseg-quote. Se reconocen por el borde izquierdo del estilo inline,
  // que se mantuvo igual en todas las generaciones del markup.
  function esCitaDelPlugin(blockquote) {
    if (blockquote.classList.contains("opencitaseg-quote")) return true;

    const estilo = (blockquote.getAttribute("style") || "")
      .replace(/\s+/g, " ")
      .toLowerCase();

    return (
      estilo.includes("3px solid #0078d4") ||
      estilo.includes("3px solid rgb(0, 120, 212)")
    );
  }

  // Poda las citas que el seguimiento citado ya tenia adentro. Sin esto, citar
  // una respuesta que a su vez citaba a otra arrastra las dos, y el contenido
  // crece en cada vuelta del intercambio.
  //
  // Devuelve null cuando el seguimiento citado no tenia texto propio (era solo
  // una cita), para que el llamador use el placeholder en vez de un bloque
  // vacio.
  function podarCitasAnidadas(html) {
    const doc = new DOMParser().parseFromString(html, "text/html");

    doc.body.querySelectorAll("blockquote").forEach((cita) => {
      if (esCitaDelPlugin(cita)) cita.remove();
    });

    if (doc.body.textContent.trim() === "" && !doc.body.querySelector("img")) {
      return null;
    }

    return doc.body.innerHTML;
  }

  // Saca las citas que ya haya en el editor antes de insertar una nueva.
  //
  // Cerrar el panel de respuesta no limpia TinyMCE, asi que citar A, cerrar y
  // citar B dejaba las dos. Y como el blockquote es mceNonEditable, el usuario
  // no puede borrar a mano la que no queria.
  //
  // Se reemplaza en lugar de acumular porque el formulario manda un solo
  // _quoted_followup_id: la tabla cites nunca registro mas de una cita por
  // respuesta, asi que mostrar dos era incoherente con lo que se persiste.
  //
  // Solo se quitan los bloques del plugin. El texto que el usuario haya
  // escrito se conserva.
  function quitarCitasDelEditor(editor) {
    editor
      .getBody()
      .querySelectorAll("blockquote.opencitaseg-quote")
      .forEach((cita) => {
        // El template agrega un <p>&nbsp;</p> despues de cada cita; sin esto,
        // cada cita descartada deja una linea en blanco acumulada.
        const siguiente = cita.nextElementSibling;
        if (
          siguiente &&
          siguiente.tagName === "P" &&
          siguiente.textContent.replace(/\u00a0/g, "").trim() === "" &&
          !siguiente.querySelector("img")
        ) {
          siguiente.remove();
        }

        cita.remove();
      });
  }

  // Quita los parrafos vacios que quedan al principio del cuerpo, tanto el que
  // TinyMCE crea por defecto como los que dejan las citas descartadas. Se corta
  // en el primer nodo con contenido, asi no toca el texto del usuario.
  function limpiarParrafosVaciosIniciales(editor) {
    const cuerpo = editor.getBody();

    while (cuerpo.firstElementChild) {
      const primero = cuerpo.firstElementChild;

      const vacio =
        primero.tagName === "P" &&
        primero.textContent.replace(/\u00a0/g, "").trim() === "" &&
        !primero.querySelector("img");

      if (!vacio) break;

      primero.remove();
    }
  }

  function inyectarBotones() {
    if (citasHabilitadas === null) return;

    const citables = document.querySelectorAll(SELECTOR_CITABLES);

    citables.forEach((item) => {
      if (item.querySelector(".btn-citar-seguimiento")) return;

      const idItem = item.getAttribute("data-items-id");
      const itemtype = item.getAttribute("data-itemtype");
      if (!idItem || !itemtype) return;

      const esTarea = itemtype !== "ITILFollowup";
      if (esTarea ? !citasHabilitadas.tareas : !citasHabilitadas.seguimientos)
        return;

      const contenedorAcciones = item.querySelector(".timeline-item-buttons");

      if (contenedorAcciones) {
        const boton = document.createElement("a");
        boton.href = "#";
        boton.className =
          "btn btn-sm btn-ghost-secondary btn-citar-seguimiento me-2";
        boton.setAttribute("data-id", idItem);
        boton.setAttribute("data-itemtype", itemtype);
        boton.title =
          itemtype === "ITILFollowup"
            ? t("Quote this followup")
            : t("Quote this task");
        boton.innerHTML = '<i class="ti ti-quote"></i> ' + t("Quote");

        contenedorAcciones.insertBefore(boton, contenedorAcciones.firstChild);
      }
    });
  }

  let resolucionEnCurso = false;

  // La timeline puede renderizarse despues del DOMContentLoaded, asi que la
  // resolucion se intenta tambien desde el MutationObserver. Se ejecuta una
  // sola vez: el guard corta tanto si ya hay resultado como si hay un fetch
  // en vuelo.
  function resolverHabilitacion() {
    if (citasHabilitadas !== null || resolucionEnCurso) return;

    const contexto = contextoItil();
    if (!contexto) return;

    resolucionEnCurso = true;

    const base = rutaBasePlugin();
    if (!base) {
      citasHabilitadas = { seguimientos: true, tareas: true };
      inyectarBotones();
      return;
    }

    fetch(
      base +
        "/ajax/isactive.php?itemtype=" +
        encodeURIComponent(contexto.itemtype) +
        "&items_id=" +
        encodeURIComponent(contexto.itemsId),
      { credentials: "same-origin" },
    )
      .then((r) => (r.ok ? r.json() : { active: false }))
      .then((data) => {
        citasHabilitadas = {
          seguimientos: data.active === true,
          tareas: data.active_tasks === true,
        };
        citaPrivadaPorDefecto = data.default_private === true;
        inyectarBotones();
      })
      .catch(() => {
        citasHabilitadas = { seguimientos: true, tareas: true };
        inyectarBotones();
      });
  }

  resolverHabilitacion();

  const observer = new MutationObserver(function (mutations) {
    let deberiamosInyectar = false;
    mutations.forEach(function (mutation) {
      if (mutation.addedNodes.length > 0) deberiamosInyectar = true;
    });
    if (deberiamosInyectar) {
      resolverHabilitacion();
      inyectarBotones();
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });

  // Al cerrar el panel de respuesta descartamos la cita pendiente.
  //
  // El bloque es mceNonEditable, asi que el usuario no puede borrarlo a mano:
  // si cito por error y cierra, al reabrir con Responder se encontraba la cita
  // ahi sin forma de sacarla, y los inputs ocultos seguian apuntando al objeto
  // citado.
  //
  // En el #6 habiamos descartado limpiar al cerrar porque se llevaba puesto el
  // borrador. Ya no aplica: quitarCitasDelEditor() saca solo los bloques del
  // plugin y el texto escrito se conserva, que es el comportamiento nativo de
  // GLPI para el borrador.
  function descartarCitaPendiente() {
    const form = document.querySelector("#new-ITILFollowup-block form");
    if (!form) return;

    form
      .querySelectorAll(
        'input[name="_quoted_followup_id"], input[name="_quoted_itemtype"]',
      )
      .forEach((input) => input.remove());

    const textarea = form.querySelector('textarea[name="content"]');
    if (!textarea || typeof tinymce === "undefined") return;

    const editor = tinymce.get(textarea.id);
    if (editor && editor.initialized) {
      quitarCitasDelEditor(editor);
      limpiarParrafosVaciosIniciales(editor);
    }
  }

  document.body.addEventListener("hidden.bs.collapse", function (e) {
    if (e.target && e.target.id === "new-ITILFollowup-block") {
      descartarCitaPendiente();
    }
  });

  // Polls until the given TinyMCE editor has finished its async init, then
  // calls onReady(editor). Calling editor.focus()/execCommand() before this
  // (e.g. right after Bootstrap starts opening the reply panel) can hit the
  // editor mid-initialization and throw, so this replaces a flat delay with
  // an actual readiness check.
  function esperarEditor(
    textareaId,
    onReady,
    onTimeout,
    intentosRestantes = 30,
  ) {
    const editor =
      typeof tinymce !== "undefined" ? tinymce.get(textareaId) : null;

    if (editor && editor.initialized) {
      onReady(editor);
      return;
    }

    if (intentosRestantes <= 0) {
      onTimeout();
      return;
    }

    setTimeout(
      () =>
        esperarEditor(textareaId, onReady, onTimeout, intentosRestantes - 1),
      100,
    );
  }

  let citaOperacionEnCurso = false;

  document.body.addEventListener("click", function (e) {
    const enlaceNavegacion = e.target.closest(
      'a.opencitaseg-quote-link, a[href^="#ITILFollowup_"]',
    );
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
    if (citasHabilitadas === null) return;
    if (!botonCitar) return;

    e.preventDefault();

    if (citaOperacionEnCurso) return;
    citaOperacionEnCurso = true;
    const liberarGuard = () => {
      citaOperacionEnCurso = false;
    };

    const idSeguimiento = botonCitar.getAttribute("data-id");
    const itemtypeCitado =
      botonCitar.getAttribute("data-itemtype") || "ITILFollowup";

    const elementoCitado = document.getElementById(
      `${itemtypeCitado}_${idSeguimiento}`,
    );

    // GLPI marca los seguimientos y tareas privadas con un span .is-private
    // dentro del timeline-item, igual en GLPI 10 y 11.
    const citadoEsPrivado = elementoCitado
      ? elementoCitado.querySelector(".is-private") !== null
      : false;

    const insertarCita = () => {
      const formularioRespuesta = document.querySelector(
        "#new-ITILFollowup-block form",
      );

      if (!formularioRespuesta) {
        liberarGuard();
        return;
      }

      aplicarPrivacidadPorDefecto(formularioRespuesta, citadoEsPrivado);

      let inputOculto = formularioRespuesta.querySelector(
        'input[name="_quoted_followup_id"]',
      );
      if (!inputOculto) {
        inputOculto = document.createElement("input");
        inputOculto.type = "hidden";
        inputOculto.name = "_quoted_followup_id";
        formularioRespuesta.appendChild(inputOculto);
      }
      inputOculto.value = idSeguimiento;

      let inputTipo = formularioRespuesta.querySelector(
        'input[name="_quoted_itemtype"]',
      );
      if (!inputTipo) {
        inputTipo = document.createElement("input");
        inputTipo.type = "hidden";
        inputTipo.name = "_quoted_itemtype";
        formularioRespuesta.appendChild(inputTipo);
      }
      inputTipo.value = itemtypeCitado;

      const elementoSeguimiento = document.getElementById(
        `${itemtypeCitado}_${idSeguimiento}`,
      );
      let textoCitado = "...";
      let autorCita = t("User");

      if (elementoSeguimiento) {
        const nodoTexto = elementoSeguimiento.querySelector(
          ".read-only-content .rich_text_container",
        );
        if (nodoTexto) {
          textoCitado = podarCitasAnidadas(nodoTexto.innerHTML) ?? "...";
        }

        const autorNodo = elementoSeguimiento.querySelector(
          '.creator span[id^="user_"] a, .creator a[href*="user.form.php"]',
        );
        if (autorNodo && autorNodo.textContent.trim() !== "") {
          autorCita = autorNodo.textContent.trim();
        }
      }

      const etiquetaCita = t("Quoting %s").replace("%s", autorCita);
      const htmlCita = `
                    <blockquote contenteditable="false" class="mceNonEditable opencitaseg-quote" style="border-left: 3px solid #0078d4; padding-left: 10px; margin-left: 0; color: #555; background-color: #f8f9fa; padding: 10px; border-radius: 4px; user-select: none;">
                        <strong><a href="#${itemtypeCitado}_${idSeguimiento}" class="opencitaseg-quote-link" style="text-decoration: none; color: #0078d4;">
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
          quitarCitasDelEditor(editor);
          limpiarParrafosVaciosIniciales(editor);
          editor.selection.setCursorLocation(editor.getBody(), 0);
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
        panelSeguimiento.addEventListener("shown.bs.collapse", insertarCita, {
          once: true,
        });
        btnToggle.click();
      } else {
        liberarGuard();
        return;
      }
    } else {
      insertarCita();
    }

    function aplicarPrivacidadPorDefecto(form, citadoEsPrivado) {
      const debePrivado = citadoEsPrivado || citaPrivadaPorDefecto;

      const checkbox = form.querySelector(
        'input[type="checkbox"][name="is_private"]',
      );

      if (checkbox) {
        // Se sincroniza en los dos sentidos: si la cita anterior era privada y
        // esta no lo es, hay que desmarcar. Sin esto la respuesta se guardaba
        // privada sin que el usuario lo pidiera.
        if (checkbox.checked !== debePrivado) {
          checkbox.checked = debePrivado;
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }

        if (citadoEsPrivado) {
          checkbox.title = t(
            "The quoted item is private, so this reply will be private too",
          );
        } else {
          checkbox.removeAttribute("title");
        }

        return;
      }

      const hidden = form.querySelector(
        'input[type="hidden"][name="is_private"]',
      );
      if (hidden) hidden.value = debePrivado ? "1" : "0";
    }
  });
});
