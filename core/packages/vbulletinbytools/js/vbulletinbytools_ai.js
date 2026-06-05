(function () {
    "use strict";

    var scriptId = "vbulletinbytools-ai-root-loader";
    var target = "/js/vbulletinbytools_ai.js?v=17";

    if (document.getElementById(scriptId)) {
        return;
    }

    var script = document.createElement("script");
    script.id = scriptId;
    script.src = target;
    script.async = false;

    document.head.appendChild(script);
})();
