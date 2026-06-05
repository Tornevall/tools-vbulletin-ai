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

    function findEditorElement() {
        var selectors = [
            "textarea[name='text']",
            "textarea[name='pagetext']",
            "textarea",
            "[contenteditable='true']",
            ".cke_wysiwyg_frame",
            ".js-editor",
            ".cke",
            ".editor",
            ".conversation-editor"
        ];

        for (var i = 0; i < selectors.length; i++) {
            var element = document.querySelector(selectors[i]);

            if (element) {
                return element;
            }
        }

        return null;
    }

    function findEditorContainer() {
        var element = findEditorElement();

        if (!element) {
            return null;
        }

        return element.closest(".editor, .cke, .js-post-editor, .conversation-editor, form") || element.parentNode;
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
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function htmlParagraphs(text) {
        var lines = String(text || "").split(/\n{2,}/);

        return lines.map(function (line) {
            return "<p>" + escapeHtml(line).replace(/\n/g, "<br>") + "</p>";
        }).join("");
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

    function getTitleText() {
        var titleInput = document.querySelector("input[name='title'], input[name='subject'], input[placeholder='Enter title']");

        if (titleInput && titleInput.value) {
            return titleInput.value;
        }

        var titleElement = document.querySelector("h1, .b-page-title, .conversation-title, .thread-title, .topic-title, .js-topic-title");

        if (titleElement) {
            return cleanText(titleElement.innerText || titleElement.textContent || "");
        }

        return document.title || "";
    }

    function getBreadcrumbText() {
        var element = document.querySelector(".breadcrumbs, .breadcrumb, .b-breadcrumb, .js-breadcrumbs, nav[aria-label='breadcrumb']");

        if (!element) {
            return "";
        }

        return cleanText(element.innerText || element.textContent || "");
    }

    function getCurrentNodeId() {
        if (window.pageData) {
            var keys = ["nodeid", "nodeId", "conversationid", "conversationId", "parentid", "parentId", "starter", "starterid", "starterId"];

            for (var i = 0; i < keys.length; i++) {
                if (window.pageData[keys[i]]) {
                    return parseInt(window.pageData[keys[i]], 10) || 0;
                }
            }
        }

        var nodeElement = document.querySelector("[data-node-id], [data-nodeid], [data-node-id32], [data-node]");

        if (nodeElement) {
            return parseInt(
                nodeElement.getAttribute("data-node-id") ||
                nodeElement.getAttribute("data-nodeid") ||
                nodeElement.getAttribute("data-node-id32") ||
                nodeElement.getAttribute("data-node"),
                10
            ) || 0;
        }

        var urlMatch = String(window.location.href || "").match(/(?:nodeid|node|p|t|topic)[=\/](\d+)/i);

        if (urlMatch && urlMatch[1]) {
            return parseInt(urlMatch[1], 10) || 0;
        }

        return 0;
    }

    function getSecurityToken() {
        var candidates = [];

        if (window.SECURITYTOKEN) {
            candidates.push(window.SECURITYTOKEN);
        }

        if (window.securitytoken) {
            candidates.push(window.securitytoken);
        }

        if (window.pageData) {
            candidates.push(window.pageData.securitytoken);
            candidates.push(window.pageData.securityToken);
            candidates.push(window.pageData.SECURITYTOKEN);
        }

        if (window.vBulletin) {
            candidates.push(window.vBulletin.securitytoken);
            candidates.push(window.vBulletin.securityToken);

            if (typeof window.vBulletin.getSecurityToken === "function") {
                candidates.push(window.vBulletin.getSecurityToken());
            }
        }

        var input = document.querySelector("input[name='securitytoken'], input[name='securityToken'], input[name='token']");

        if (input && input.value) {
            candidates.push(input.value);
        }

        var meta = document.querySelector("meta[name='securitytoken'], meta[name='securityToken'], meta[name='csrf-token']");

        if (meta && meta.getAttribute("content")) {
            candidates.push(meta.getAttribute("content"));
        }

        for (var i = 0; i < candidates.length; i++) {
            var value = String(candidates[i] || "").trim();

            if (value && value !== "undefined" && value !== "null") {
                return value;
            }
        }

        return "";
    }

    function detectInstructionLanguage(text) {
        var value = String(text || "").toLowerCase();

        if (value.indexOf(" in english") !== -1 || value.indexOf("på engelska") !== -1) {
            return "en";
        }

        if (value.indexOf("på svenska") !== -1 || value.indexOf(" skriv") !== -1 || value.indexOf("kan du") !== -1) {
            return "sv";
        }

        var htmlLang = String(document.documentElement.getAttribute("lang") || "").toLowerCase();

        if (htmlLang.indexOf("sv") === 0) {
            return "sv";
        }

        if (htmlLang.indexOf("en") === 0) {
            return "en";
        }

        return "sv";
    }

    function getThreadContext() {
        var posts = [];
        var seen = {};
        var elements = document.querySelectorAll(".js-post, .b-post, .postbit, .post, .conversation-content, .js-conversation-content, .b-comment, .comment, .message, .js-message, .topic-item, .b-topic, .js-topic");

        for (var i = 0; i < elements.length; i++) {
            var text = cleanText(elements[i].innerText || elements[i].textContent || "");

            if (!text || text.length < 20 || seen[text]) {
                continue;
            }

            seen[text] = true;
            posts.push("Thread post " + (posts.length + 1) + ":\n" + text);

            if (posts.length >= MAX_POSTS) {
                break;
            }
        }

        return posts.join("\n\n");
    }

    function trimCodeBlock(code) {
        return String(code || "").replace(/^\n+/, "").replace(/\n+$/, "");
    }

    function convertMarkdownListsToBbcode(text) {
        var lines = String(text || "").split("\n");
        var output = [];
        var listType = "";

        function closeList() {
            if (listType) {
                output.push("[/list]");
                listType = "";
            }
        }

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var unordered = line.match(/^\s*[-*+]\s+(.+)$/);
            var ordered = line.match(/^\s*\d+[.)]\s+(.+)$/);

            if (unordered) {
                if (listType !== "ul") {
                    closeList();
                    output.push("[list]");
                    listType = "ul";
                }

                output.push("[*]" + unordered[1]);
                continue;
            }

            if (ordered) {
                if (listType !== "ol") {
                    closeList();
                    output.push("[list=1]");
                    listType = "ol";
                }

                output.push("[*]" + ordered[1]);
                continue;
            }

            closeList();
            output.push(line);
        }

        closeList();
        return output.join("\n");
    }

    function markdownToBbcode(markdown) {
        var text = String(markdown || "").replace(/\r\n/g, "\n").replace(/\r/g, "\n");
        var codeBlocks = [];

        text = text.replace(/```([a-z0-9_-]+)?\n([\s\S]*?)```/gi, function (match, language, code) {
            var token = "@@VBT_CODE_BLOCK_" + codeBlocks.length + "@@";
            codeBlocks.push("[code]" + trimCodeBlock(code) + "[/code]");
            return token;
        });

        text = text.replace(/!\[([^\]]*)\]\(([^\s)]+)(?:\s+\"[^\"]*\")?\)/g, function (match, alt, url) {
            return "[img]" + url + "[/img]";
        });

        text = text.replace(/\[([^\]]+)\]\(([^\s)]+)(?:\s+\"[^\"]*\")?\)/g, function (match, label, url) {
            return "[url=" + url + "]" + label + "[/url]";
        });

        text = text.replace(/^#{1,6}\s+(.+)$/gm, "[b]$1[/b]");
        text = text.replace(/^>\s?(.+)$/gm, "[quote]$1[/quote]");
        text = convertMarkdownListsToBbcode(text);
        text = text.replace(/\*\*([^*\n][\s\S]*?[^*\n])\*\*/g, "[b]$1[/b]");
        text = text.replace(/__([^_\n][\s\S]*?[^_\n])__/g, "[b]$1[/b]");
        text = text.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, "$1[i]$2[/i]");
        text = text.replace(/(^|[^_])_([^_\n]+)_(?!_)/g, "$1[i]$2[/i]");
        text = text.replace(/`([^`\n]+)`/g, "[icode]$1[/icode]");
        text = text.replace(/^\s*---\s*$/gm, "");

        for (var i = 0; i < codeBlocks.length; i++) {
            text = text.replace("@@VBT_CODE_BLOCK_" + i + "@@", codeBlocks[i]);
        }

        return text.replace(/\n{3,}/g, "\n\n").trim();
    }

    function extractAiText(response) {
        if (!response) {
            return "";
        }

        if (response.gateway_ok === false && typeof response.text === "string") {
            return response.text;
        }

        if (response.response && response.response.gateway_ok === false && typeof response.response.text === "string") {
            return response.response.text;
        }

        if (response.response && typeof response.response.response === "string") {
            return response.response.response;
        }

        if (response.response && response.response.response && typeof response.response.response.response === "string") {
            return response.response.response.response;
        }

        if (response.response && typeof response.response.text === "string") {
            return response.response.text;
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

        return JSON.stringify(response, null, 2);
    }

    function formatAjaxError(error) {
        if (!error) {
            return "Unknown error.";
        }

        if (typeof error === "string") {
            return error;
        }

        if (error.text) {
            return error.text;
        }

        if (error.message && error.raw_preview) {
            return error.message + "\nStatus: " + (error.status || "unknown") + "\n\n" + error.raw_preview;
        }

        if (error.raw_preview) {
            return error.raw_preview;
        }

        if (error.responseText) {
            return error.responseText;
        }

        return JSON.stringify(error, null, 2);
    }

    function insertIntoEditor(text) {
        var bbcode = markdownToBbcode(text);
        var ckeditor = getCkEditorInstance();

        if (ckeditor) {
            var current = ckeditor.getData() || "";
            var currentText = stripHtml(current).trim();
            var separator = currentText ? "\n\n" : "";
            ckeditor.setData(htmlParagraphs(currentText + separator + bbcode));
            return true;
        }

        var textarea = document.querySelector("textarea[name='text'], textarea[name='pagetext'], textarea");

        if (textarea) {
            var existing = textarea.value || "";
            textarea.value = existing + (existing.trim() ? "\n\n" : "") + bbcode;
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
            textarea.dispatchEvent(new Event("change", { bubbles: true }));
            return true;
        }

        var editable = document.querySelector("[contenteditable='true']");

        if (editable) {
            editable.innerText = (editable.innerText || "") + "\n\n" + bbcode;
            editable.dispatchEvent(new Event("input", { bubbles: true }));
            return true;
        }

        return false;
    }

    function callAi(promptText) {
        var title = getTitleText();
        var breadcrumbs = getBreadcrumbText();
        var threadContext = getThreadContext();
        var editorText = getEditorText();
        var language = detectInstructionLanguage(promptText);
        var nodeid = getCurrentNodeId();
        var securitytoken = getSecurityToken();

        var context = [
            "Page: vBulletin editor",
            nodeid ? "Current node ID:\n" + nodeid : "",
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
            "- Formatting may be returned as Markdown. The forum client will convert Markdown to BBCode before insertion.",
            "",
            "User request:",
            promptText
        ].join("\n");

        var body = new URLSearchParams({
            context: context,
            prompt: finalPrompt,
            language: language,
            nodeid: nodeid
        });

        if (securitytoken) {
            body.append("securitytoken", securitytoken);
        }

        return fetch(API_CALL, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json, text/javascript, */*; q=0.01",
                "X-Requested-With": "XMLHttpRequest"
            },
            credentials: "same-origin",
            body: body.toString()
        })
            .then(function (response) {
                return response.text().then(function (rawText) {
                    var parsed = null;

                    try {
                        parsed = rawText ? JSON.parse(rawText) : null;
                    } catch (e) {
                        throw {
                            message: "Invalid JSON response from vBulletin endpoint.",
                            status: response.status,
                            raw_preview: rawText.substring(0, 2000)
                        };
                    }

                    if (!response.ok) {
                        throw parsed || {
                            message: "HTTP error from vBulletin endpoint.",
                            status: response.status,
                            raw_preview: rawText.substring(0, 2000)
                        };
                    }

                    return parsed;
                });
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
            "<textarea class='vbulletinbytools-ai-prompt' placeholder='Vad vill du ha hjälp med? Ex: skriv om, sammanfatta, skapa ett svar på tråden...'></textarea>",
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
            result.innerHTML = "<div class='vbulletinbytools-ai-loading'><div class='vbulletinbytools-ai-loading-text'>Tänker...</div><div class='vbulletinbytools-ai-progress'><div class='vbulletinbytools-ai-progress-bar'></div></div></div>";
            resultActions.classList.add("h-hide");

            callAi(promptText)
                .then(function (response) {
                    lastAnswer = markdownToBbcode(extractAiText(response));
                    result.textContent = lastAnswer;
                    resultActions.classList.remove("h-hide");
                })
                .catch(function (error) {
                    result.textContent = "AI-anropet misslyckades:\n" + formatAjaxError(error);
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

        if (!editorContainer || !editorContainer.parentNode) {
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
        button.addEventListener("click", createPanel);

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
