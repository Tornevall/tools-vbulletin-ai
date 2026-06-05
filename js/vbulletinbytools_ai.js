(function () {
    "use strict";

    var API_CALL = "/ajax/api/vbulletinbytools:Ai/respond";
    var BUTTON_ID = "vbulletinbytools-ai-button";
    var BUTTON_ROW_ID = "vbulletinbytools-ai-button-row";
    var PANEL_ID = "vbulletinbytools-ai-panel";
    var RESULT_ID = "vbulletinbytools-ai-result";
    var MAX_CONTEXT_CHARS = 6000;
    var MAX_POSTS = 5;

    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    function findEditorContainer() {
        var candidates = [
            ".js-editor",
            ".cke",
            ".cke_editor_editor",
            ".editor",
            ".editor-container",
            ".conversation-editor",
            ".js-new-content-text",
            "textarea[name='text']",
            "textarea[name='pagetext']",
            "textarea",
            "[contenteditable='true']"
        ];

        for (var i = 0; i < candidates.length; i++) {
            var element = document.querySelector(candidates[i]);

            if (element) {
                return element.closest(".editor, .cke, .js-post-editor, .conversation-editor") || element.parentNode;
            }
        }

        return null;
    }

    function getTitleText() {
        var titleInput = document.querySelector("input[name='title'], input[name='subject'], input[placeholder='Enter title']");

        if (titleInput && titleInput.value) {
            return titleInput.value;
        }

        var titleSelectors = [
            "h1",
            ".b-page-title",
            ".conversation-title",
            ".thread-title",
            ".topic-title",
            ".js-topic-title"
        ];

        for (var i = 0; i < titleSelectors.length; i++) {
            var titleElement = document.querySelector(titleSelectors[i]);

            if (titleElement) {
                var text = cleanText(titleElement.innerText || titleElement.textContent || "");

                if (text) {
                    return text;
                }
            }
        }

        return document.title || "";
    }

    function detectInstructionLanguage(text) {
        var value = String(text || "").toLowerCase();

        var swedishHits = countMatches(value, [
            "skriv", "gör", "hjälp", "förklara", "sammanfatta", "översätt",
            "svenska", "detta", "kort", "långt", "svara", "avskedsbrev",
            "på svenska", "kan du", "jag vill", "tacka", "beröm"
        ]);

        var englishHits = countMatches(value, [
            "write", "make", "help", "explain", "summarize", "translate",
            "english", "this", "short", "long", "reply", "answer",
            "in english", "can you", "i want", "thank", "praise"
        ]);

        var norwegianHits = countMatches(value, [
            "skriv", "gjør", "hjelp", "forklar", "oppsummer",
            "norsk", "dette", "kort", "svar", "på norsk"
        ]);

        var danishHits = countMatches(value, [
            "skriv", "gør", "hjælp", "forklar", "opsummer",
            "dansk", "dette", "kort", "svar", "på dansk"
        ]);

        var finnishHits = countMatches(value, [
            "kirjoita", "tee", "auta", "selitä", "tiivistä",
            "suomi", "suomeksi", "tämä", "lyhyt", "vastaa"
        ]);

        var germanHits = countMatches(value, [
            "schreib", "schreibe", "mach", "hilfe", "erkläre",
            "deutsch", "kurz", "antwort", "auf deutsch"
        ]);

        var frenchHits = countMatches(value, [
            "écris", "ecris", "aide", "explique", "résume", "resume",
            "français", "francais", "réponds", "reponds"
        ]);

        var spanishHits = countMatches(value, [
            "escribe", "haz", "ayuda", "explica", "resume",
            "español", "espanol", "responde"
        ]);

        var scores = [
            { language: "sv", score: swedishHits },
            { language: "en", score: englishHits },
            { language: "no", score: norwegianHits },
            { language: "da", score: danishHits },
            { language: "fi", score: finnishHits },
            { language: "de", score: germanHits },
            { language: "fr", score: frenchHits },
            { language: "es", score: spanishHits }
        ];

        scores.sort(function (a, b) {
            return b.score - a.score;
        });

        if (scores[0].score > 0) {
            return scores[0].language;
        }

        return "sv";
    }

    function countMatches(text, words) {
        var count = 0;

        for (var i = 0; i < words.length; i++) {
            if (text.indexOf(words[i]) !== -1) {
                count++;
            }
        }

        return count;
    }

    function getCkEditorInstance() {
        if (typeof window.CKEDITOR === "undefined" || !window.CKEDITOR.instances) {
            return null;
        }

        var names = Object.keys(window.CKEDITOR.instances);

        if (!names.length) {
            return null;
        }

        return window.CKEDITOR.instances[names[0]];
    }

    function getEditorText() {
        var ckeditor = getCkEditorInstance();

        if (ckeditor) {
            return stripHtml(ckeditor.getData());
        }

        var textarea = document.querySelector("textarea[name='text'], textarea[name='pagetext'], textarea");

        if (textarea) {
            return textarea.value || "";
        }

        var editable = document.querySelector("[contenteditable='true']");

        if (editable) {
            return editable.innerText || editable.textContent || "";
        }

        return "";
    }

    function getBreadcrumbText() {
        var selectors = [
            ".breadcrumbs",
            ".breadcrumb",
            ".b-breadcrumb",
            ".js-breadcrumbs",
            "nav[aria-label='breadcrumb']"
        ];

        for (var i = 0; i < selectors.length; i++) {
            var element = document.querySelector(selectors[i]);

            if (element) {
                var text = cleanText(element.innerText || element.textContent || "");

                if (text) {
                    return text;
                }
            }
        }

        return "";
    }

    function getThreadContext() {
        var posts = [];
        var seen = {};
        var postSelectors = [
            ".js-post",
            ".b-post",
            ".postbit",
            ".post",
            ".conversation-content",
            ".js-conversation-content",
            ".b-comment",
            ".comment",
            ".message",
            ".js-message",
            ".topic-item",
            ".b-topic",
            ".js-topic"
        ];

        for (var i = 0; i < postSelectors.length; i++) {
            var elements = document.querySelectorAll(postSelectors[i]);

            for (var j = 0; j < elements.length; j++) {
                var postText = extractPostText(elements[j]);

                if (!postText) {
                    continue;
                }

                if (seen[postText]) {
                    continue;
                }

                seen[postText] = true;
                posts.push(postText);

                if (posts.length >= MAX_POSTS) {
                    break;
                }
            }

            if (posts.length >= MAX_POSTS) {
                break;
            }
        }

        if (!posts.length) {
            return "";
        }

        return posts.map(function (post, index) {
            return "Thread post " + (index + 1) + ":\n" + post;
        }).join("\n\n");
    }

    function extractPostText(element) {
        if (!element) {
            return "";
        }

        var clone = element.cloneNode(true);

        removeNoise(clone);

        var author = extractAuthor(element);
        var text = cleanText(clone.innerText || clone.textContent || "");

        if (!text) {
            return "";
        }

        if (text.length < 20) {
            return "";
        }

        if (author) {
            return "Author: " + author + "\n" + text;
        }

        return text;
    }

    function extractAuthor(element) {
        var selectors = [
            ".username",
            ".author",
            ".userinfo .name",
            ".b-userinfo__username",
            ".js-user-name",
            "a[href*='/member/']",
            "a[href*='member.php']"
        ];

        for (var i = 0; i < selectors.length; i++) {
            var authorElement = element.querySelector(selectors[i]);

            if (authorElement) {
                var text = cleanText(authorElement.innerText || authorElement.textContent || "");

                if (text) {
                    return text;
                }
            }
        }

        return "";
    }

    function removeNoise(root) {
        var noiseSelectors = [
            "script",
            "style",
            "noscript",
            ".signature",
            ".sig",
            ".post-signature",
            ".b-post__signature",
            ".js-post-signature",
            ".quote-controls",
            ".post-controls",
            ".b-post-control",
            ".js-post-menu",
            ".reactions",
            ".reaction",
            ".attachments",
            ".attachment",
            ".avatar",
            ".user-avatar",
            ".js-avatar",
            ".moderation",
            ".admin-controls",
            ".h-hide",
            ".hidden",
            "#vbulletinbytools-ai-panel",
            "#vbulletinbytools-ai-button-row"
        ];

        for (var i = 0; i < noiseSelectors.length; i++) {
            var elements = root.querySelectorAll(noiseSelectors[i]);

            for (var j = 0; j < elements.length; j++) {
                elements[j].remove();
            }
        }
    }

    function cleanText(text) {
        return String(text || "")
            .replace(/\r/g, "\n")
            .replace(/[ \t]+/g, " ")
            .replace(/\n[ \t]+/g, "\n")
            .replace(/[ \t]+\n/g, "\n")
            .replace(/\n{3,}/g, "\n\n")
            .trim();
    }

    function limitText(text, maxLength) {
        text = String(text || "");

        if (text.length <= maxLength) {
            return text;
        }

        return text.substring(0, maxLength) + "\n\n[Context truncated]";
    }

    function insertIntoEditor(text) {
        var ckeditor = getCkEditorInstance();

        if (ckeditor) {
            var current = ckeditor.getData() || "";
            var separator = current.trim() ? "<p>&nbsp;</p>" : "";

            ckeditor.setData(current + separator + htmlParagraphs(text));
            return true;
        }

        var textarea = document.querySelector("textarea[name='text'], textarea[name='pagetext'], textarea");

        if (textarea) {
            var existing = textarea.value || "";
            textarea.value = existing + (existing.trim() ? "\n\n" : "") + text;
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
            textarea.dispatchEvent(new Event("change", { bubbles: true }));
            return true;
        }

        var editable = document.querySelector("[contenteditable='true']");

        if (editable) {
            editable.innerText = (editable.innerText || "") + "\n\n" + text;
            editable.dispatchEvent(new Event("input", { bubbles: true }));
            return true;
        }

        return false;
    }

    function stripHtml(html) {
        var div = document.createElement("div");
        div.innerHTML = html || "";
        return div.textContent || div.innerText || "";
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function htmlParagraphs(text) {
        var lines = String(text || "").split(/\n{2,}/);

        return lines.map(function (line) {
            return "<p>" + escapeHtml(line).replace(/\n/g, "<br>") + "</p>";
        }).join("");
    }

    function extractAiText(response) {
        if (!response) {
            return "";
        }

        if (response.response && typeof response.response.response === "string") {
            return response.response.response;
        }

        if (response.response && typeof response.response.raw_preview === "string") {
            return "Servern fick ett ogiltigt svar från Tornevall Tools:\n\n" + response.response.raw_preview;
        }

        if (typeof response.response === "string") {
            return response.response;
        }

        if (typeof response.text === "string") {
            return response.text;
        }

        if (response.error) {
            return response.error;
        }

        if (response.raw_preview) {
            return response.raw_preview;
        }

        if (response.raw) {
            return response.raw;
        }

        return JSON.stringify(response, null, 2);
    }

    function callAi(promptText) {
        var title = getTitleText();
        var breadcrumbs = getBreadcrumbText();
        var threadContext = getThreadContext();
        var editorText = getEditorText();
        var language = detectInstructionLanguage(promptText);

        var context = [
            "Page: vBulletin editor",
            title ? "Topic title:\n" + title : "",
            breadcrumbs ? "Breadcrumbs:\n" + breadcrumbs : "",
            threadContext ? "Visible thread context:\n" + threadContext : "",
            editorText ? "Current editor text:\n" + editorText : ""
        ].filter(Boolean).join("\n\n");

        context = limitText(context, MAX_CONTEXT_CHARS);

        var finalPrompt = [
            "Output rules:",
            "- Return only the requested content.",
            "- Do not add introductions such as \"Självklart\", \"Här är\", \"Sure\", \"Here is\", or similar.",
            "- Do not add closing remarks such as \"Hoppas detta hjälper\", \"Hope this helps\", or similar.",
            "- Do not explain what you are doing unless the user explicitly asks for an explanation.",
            "- Write in the same language as the user's instruction.",
            "- If the user's instruction explicitly asks for a specific language, use that requested language instead.",
            "",
            "User request:",
            promptText
        ].join("\n");

        return new Promise(function (resolve, reject) {
            if (window.vBulletin && typeof window.vBulletin.AJAX === "function") {
                window.vBulletin.AJAX({
                    call: API_CALL,
                    data: {
                        context: context,
                        prompt: finalPrompt,
                        language: language
                    },
                    success: function (response) {
                        resolve(response);
                    },
                    api_error: function (error) {
                        reject(error);
                    },
                    error: function (error) {
                        reject(error);
                    }
                });

                return;
            }

            fetch(API_CALL, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                credentials: "same-origin",
                body: new URLSearchParams({
                    context: context,
                    prompt: finalPrompt,
                    language: language
                }).toString()
            })
                .then(function (response) {
                    return response.json();
                })
                .then(resolve)
                .catch(reject);
        });
    }

    function createPanel() {
        var existing = document.getElementById(PANEL_ID);

        if (existing) {
            existing.remove();
            return;
        }

        var panel = document.createElement("div");
        panel.id = PANEL_ID;
        panel.className = "vbulletinbytools-ai-panel";

        panel.innerHTML = [
            "<div class='vbulletinbytools-ai-panel-title'>Ask AI for help</div>",
            "<textarea class='vbulletinbytools-ai-prompt' placeholder='Vad vill du ha hjälp med? Ex: Skriv om detta mer sakligt, korta ner texten, skapa ett svar på tråden...'></textarea>",
            "<div class='vbulletinbytools-ai-actions'>",
            "  <button type='button' class='vbulletinbytools-ai-send'>Ask AI</button>",
            "  <button type='button' class='vbulletinbytools-ai-close'>Close</button>",
            "</div>",
            "<pre id='" + RESULT_ID + "' class='vbulletinbytools-ai-result'></pre>",
            "<div class='vbulletinbytools-ai-actions vbulletinbytools-ai-result-actions h-hide'>",
            "  <button type='button' class='vbulletinbytools-ai-insert'>Insert answer</button>",
            "</div>"
        ].join("");

        var buttonRow = document.getElementById(BUTTON_ROW_ID);

        if (buttonRow && buttonRow.parentNode) {
            buttonRow.parentNode.insertBefore(panel, buttonRow.nextSibling);
            bindPanel(panel);
            return;
        }

        var editorContainer = findEditorContainer();

        if (editorContainer && editorContainer.parentNode) {
            editorContainer.parentNode.insertBefore(panel, editorContainer);
        } else {
            document.body.appendChild(panel);
        }

        bindPanel(panel);
    }

    function bindPanel(panel) {
        var prompt = panel.querySelector(".vbulletinbytools-ai-prompt");
        var send = panel.querySelector(".vbulletinbytools-ai-send");
        var close = panel.querySelector(".vbulletinbytools-ai-close");
        var insert = panel.querySelector(".vbulletinbytools-ai-insert");
        var result = panel.querySelector("#" + RESULT_ID);
        var resultActions = panel.querySelector(".vbulletinbytools-ai-result-actions");
        var lastAnswer = "";

        close.addEventListener("click", function () {
            panel.remove();
        });

        send.addEventListener("click", function () {
            var promptText = (prompt.value || "").trim();

            if (!promptText) {
                result.textContent = "Skriv vad AI ska hjälpa till med först.";
                return;
            }

            send.disabled = true;
            result.innerHTML = [
                "<div class='vbulletinbytools-ai-loading'>",
                "  <div class='vbulletinbytools-ai-loading-text'>Tänker...</div>",
                "  <div class='vbulletinbytools-ai-progress'>",
                "    <div class='vbulletinbytools-ai-progress-bar'></div>",
                "  </div>",
                "</div>"
            ].join("");
            resultActions.classList.add("h-hide");

            callAi(promptText)
                .then(function (response) {
                    lastAnswer = extractAiText(response);
                    result.textContent = lastAnswer;
                    resultActions.classList.remove("h-hide");
                })
                .catch(function (error) {
                    result.textContent = "AI-anropet misslyckades:\n" + JSON.stringify(error, null, 2);
                })
                .finally(function () {
                    send.disabled = false;
                });
        });

        insert.addEventListener("click", function () {
            if (!lastAnswer) {
                return;
            }

            if (!insertIntoEditor(lastAnswer)) {
                result.textContent = lastAnswer + "\n\nKunde inte hitta editorn automatiskt.";
            }
        });

        prompt.focus();
    }

    function addButton() {
        if (document.getElementById(BUTTON_ID)) {
            return;
        }

        var editorContainer = findEditorContainer();

        if (!editorContainer) {
            return;
        }

        var buttonRow = document.createElement("div");
        buttonRow.id = BUTTON_ROW_ID;
        buttonRow.className = "vbulletinbytools-ai-button-row";

        var button = document.createElement("button");
        button.id = BUTTON_ID;
        button.type = "button";
        button.className = "vbulletinbytools-ai-button";
        button.textContent = "Ask AI for help";

        button.addEventListener("click", function () {
            createPanel();
        });

        buttonRow.appendChild(button);

        editorContainer.parentNode.insertBefore(buttonRow, editorContainer);
    }

    function boot() {
        addButton();

        var observer = new MutationObserver(function () {
            addButton();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    ready(boot);
})();
