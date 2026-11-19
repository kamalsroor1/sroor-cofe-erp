package com.ibraa.app;

import com.getcapacitor.Plugin;
import java.util.Collections;
import java.util.List;

/** MOB-03: plugins registered only in the "play" flavor. No AppUpdater: Play owns updates. */
final class FlavorPlugins {

    private FlavorPlugins() {}

    static List<Class<? extends Plugin>> all() {
        return Collections.singletonList(PlayStoreBuildPlugin.class);
    }
}
