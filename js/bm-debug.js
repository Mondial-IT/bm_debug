/*
 * Copyright (c) Mondial-IT BV - Blue Marloc 2024
 *   Created on 2024-11-21 at 11:32:11
 */

console.log('%c BMDebug: %c BMDebug.js is running Ok  ', BM.log.color1, BM.log.color2);


(function (Drupal) {
  Drupal.behaviors.bm_debug = {
    attach: function (context, settings) {
      console.log("%c bm_debug %c is running", BM.log.color1, BM.log.color2)
      if (window.BM['BMDebug']) {
        window.BM.BMDebug.cleanUp()
      }
    }
  };
})(Drupal);

class BMDebug {

  constructor() {

    setTimeout(this.cleanUp, 2000)
  }

  // removes "$message string (33) "---temporary-fix-see-issue-252---""
  cleanUp() {

    document.querySelectorAll('dt').forEach(el => {
        if (el.innerHTML.indexOf('---temporary') !== -1) {
          el.classList.add('displaynone')
        }
      }
    )
    document.querySelectorAll('li.messages__item').forEach((item) => {
        removeCharactersFromTextNodes(item, / => /g);
      }
    )
    const kintRichDivs = document.querySelectorAll(".kint-rich");

    kintRichDivs.forEach(function (d) {
      const textNodes = Array.from(d.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);

      textNodes.forEach(node => {
        if (node.textContent.trim() === ':') {
          // Remove the text node if it only contains ": "
          node.remove();
        }
      });
    });

    function removeCharactersFromTextNodes(element, charactersToRemove) {
      const childNodes = element.childNodes;
      childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          // Replace characters in text node
          node.nodeValue = node.nodeValue.replace(charactersToRemove, '');
        }
        // else if (node.nodeType === Node.ELEMENT_NODE) {
        //   // Recursively handle child elements
        //   removeCharactersFromTextNodes(node, charactersToRemove);
        // }
      });

    }
  }
}


if (window.BM === undefined) {
  window['BM'] = {}
}
window.BM['BMDebug'] = new BMDebug();

/**
 * endpoint error handler when all previous fail.
 */
window.addEventListener('error', function (event) {
  // Prevent the default browser error handling
  event.preventDefault();

  let message = parseBrokenKint(event.message);

  // Format the error message
  const errorMessage = `
        <h2>Blue Marloc detected an error</h2>
        <p><strong>Message:</strong> ${message}</p>

        <p><strong>File:</strong> ${event.filename}</p>
        <p><strong>Line:</strong> ${event.lineno}</p>
        <p><strong>Column:</strong> ${event.colno}</p>
        <p><details><summary></summary>${event.error.stack}</details></p>

    `;

  // Create the dialog container
  const dialogContainer = document.createElement('div');
  dialogContainer.style.position = 'fixed';
  dialogContainer.style.top = '50%';
  dialogContainer.style.left = '50%';
  dialogContainer.style.transform = 'translate(-50%, -50%)';
  dialogContainer.style.backgroundColor = '#f1f9ff';
  dialogContainer.style.padding = '20px';
  dialogContainer.style.border = '2px solid #139ad0';
  dialogContainer.style.zIndex = '10000';
  dialogContainer.style.maxWidth = '80%';
  dialogContainer.style.boxShadow = '0px 0px 15px rgba(0,0,0,0.5)';
  dialogContainer.style.maxHeight = '80%';
  dialogContainer.style.overflow = 'auto';

  // Add the error message to the dialog
  dialogContainer.innerHTML = errorMessage;

  // Create a close button
  const closeButton = document.createElement('button');
  closeButton.innerText = 'Close';
  closeButton.style.marginTop = '10px';
  closeButton.onclick = function () {
    document.body.removeChild(dialogContainer);
  };

  // Append the close button to the dialog
  dialogContainer.appendChild(closeButton);

  // Append the dialog to the body
  document.body.appendChild(dialogContainer);
});

function parseBrokenKint(logText) {

  // proceed only when it is a failed Kint interpretation
  if (logText.indexOf('Uncaught AjaxError:') === -1) {
    return logText
  }


  // Split the log text into embedded js/css and the markup
  const htmlFragment = logText.split('.kint-highlight{background:#f0eb96}');
  return htmlFragment[1];// the message without script and css pre-amble

  /*
   not working, trying reinit kint on the message, but no luck yet
   * @type {HTMLDivElement}

  const kintContainer = document.createElement('div');
  kintContainer.id='kintContainer';

  kintContainer.innerHTML = htmlFragment[1];
  document.body.appendChild(kintContainer);

  // Dynamically load Kint CSS and JavaScript
  const kintCSS = document.createElement('link');
  kintCSS.rel = 'stylesheet';
  kintCSS.href = '/modules/custom/bm_debug/css/aante-light.css';
  document.head.appendChild(kintCSS);

  // Dynamically load Kint shared.js
  const sharedJS = document.createElement('script');
  sharedJS.src =  '/modules/custom/bm_debug/js/shared.js' // Replace with the correct path to shared.js
  sharedJS.onload = () => {
    console.log('Kint shared.js loaded')

    // Dynamically load Kint rich.js
    const richJS = document.createElement('script')
    richJS.src =  '/modules/custom/bm_debug/js/rich.js' // Replace with the correct path to rich.js
    richJS.onload = () => {
      console.log('Kint rich.js loaded')
      initializeKintRich()
    }
    document.head.appendChild(richJS)
  }
  document.head.appendChild(sharedJS)


   Kint.init(kintContainer);

   */

}

function initializeKintRich() {
  if (typeof kintRich !== 'undefined' && kintRich.init) {
    const kintContainers = document.querySelectorAll('.kint-rich'); // Target
                                                                    // Kint
                                                                    // rich
                                                                    // dumps
    kintContainers.forEach((container) => {
      try {
        kintRich.init(container); // Initialize rich mode for each container
      }
      catch (e) {
        console.error('Error initializing Kint rich mode:', e);
      }
    });
  }
  else {
    console.error('kintRich is not defined or initialization function is missing.');
  }
}
