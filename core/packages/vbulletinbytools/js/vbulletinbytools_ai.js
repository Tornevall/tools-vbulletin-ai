(function () {
    "use strict";

    var scriptId = "vbulletinbytools-ai-root-loader";
    var cssId = "vbulletinbytools-ai-root-css-loader";
    var scriptTarget = "/js/vbulletinbytools_ai.js?v=20";
    var cssTarget = "/js/vbulletinbytools_ai.css?v=20";

    if (!document.getElementById(cssId)) {
        var link = document.createElement("link");
        link.id = cssId;
        link.rel = "stylesheet";
        link.href = cssTarget;
        document.head.appendChild(link);
    }

    if (document.getElementById(scriptId)) {
        return;
    }

    var script = document.createElement("script");
    script.id = scriptId;
    script.src = scriptTarget;
    script.async = false;

    document.head.appendChild(script);
})();
