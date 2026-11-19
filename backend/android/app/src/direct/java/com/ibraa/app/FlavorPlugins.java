package com.ibraa.app;

import com.getcapacitor.Plugin;
import java.util.Collections;
import java.util.List;

/** MOB-03: plugins registered only in the "direct" flavor (self-update through a verified APK). */
final class FlavorPlugins {

    private FlavorPlugins() {}

    static List<Class<? extends Plugin>> all() {
        return Collections.singletonList(AppUpdaterPlugin.class);
    }
}
