(function () {
    'use strict';

    const endpoint = '/core/packages/vbulletin_by_tools/api/ai.php';

    function findEditor() {
        return document.querySelector('[contenteditable="true"]') ||
            document.querySelector('textarea[name="message"]') ||
            document.querySelector('textarea');
    }

    function getEditorText(editor) {
        if (!editor) {
            return '';
        }

        if (editor.value !== undefined) {
            return editor.value;
        }

        return editor.innerText || editor.textContent || '';
    }

    function setEditorText(editor, text) {
        if (!editor) {
            return;
        }

        if (editor.value !== undefined) {
            editor.value = text;
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        editor.focus();
        editor.innerText = text;
        editor.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function unwrapGatewayText(data) {
        if (!data || !data.ok) {
            throw new Error((data && data.error) || 'AI request failed.');
        }

        const response = data.response || {};

        if (typeof response === 'string') {
            return response;
        }

        if (typeof response.text === 'string') {
            return response.text;
        }

        if (typeof response.answer === 'string') {
            return response.answer;
        }

        if (typeof response.content === 'string') {
            return response.content;
        }

        if (response.data && typeof response.data.text === 'string') {
            return response.data.text;
        }

        return JSON.stringify(response, null, 2);
    }

    async function askAi(prompt, context) {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                prompt: prompt,
                context: context,
                language: 'sv'
            })
        });

        const data = await response.json();
        return unwrapGatewayText(data);
    }

    function createPanel(editor) {
        if (document.getElementById('tornis-tools-ai-panel')) {
            return;
        }

        const panel = document.createElement('div');
        panel.id = 'tornis-tools-ai-panel';
        panel.className = 'tornis-tools-ai-panel';

        const improve = document.createElement('button');
        improve.type = 'button';
        improve.textContent = 'AI: Förbättra text';

        const shorten = document.createElement('button');
        shorten.type = 'button';
        shorten.textContent = 'AI: Förkorta';

        const custom = document.createElement('button');
        custom.type = 'button';
        custom.textContent = 'AI: Egen instruktion';

        const status = document.createElement('span');
        status.className = 'tornis-tools-ai-status';

        async function run(prompt) {
            const currentText = getEditorText(editor);
            status.textContent = 'Tänker...';

            try {
                const result = await askAi(prompt, currentText);
                setEditorText(editor, result);
                status.textContent = 'Klart.';
            } catch (error) {
                status.textContent = error.message;
            }
        }

        improve.addEventListener('click', function () {
            run('Skriv om texten så att den blir tydligare, mer saklig och bättre formulerad. Behåll språk och ungefärlig innebörd.');
        });

        shorten.addEventListener('click', function () {
            run('Förkorta texten utan att tappa poängen. Behåll språk och saklighet.');
        });

        custom.addEventListener('click', function () {
            const prompt = window.prompt('Vad ska AI göra med texten?');
            if (prompt) {
                run(prompt);
            }
        });

        panel.appendChild(improve);
        panel.appendChild(shorten);
        panel.appendChild(custom);
        panel.appendChild(status);

        editor.parentNode.insertBefore(panel, editor);
    }

    function boot() {
        const editor = findEditor();

        if (!editor) {
            return;
        }

        createPanel(editor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
