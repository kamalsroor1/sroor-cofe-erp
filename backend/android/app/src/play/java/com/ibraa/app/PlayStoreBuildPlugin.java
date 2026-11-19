package com.ibraa.app;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * MOB-03: marker plugin of the Google Play build. The web layer checks
 * Capacitor.isPluginAvailable('PlayStoreBuild') and then reports platform=android_play to the update
 * API, which never offers it an APK (updates come from the Play Store).
 */
@CapacitorPlugin(name = "PlayStoreBuild")
public class PlayStoreBuildPlugin extends Plugin {

    @PluginMethod
    public void info(PluginCall call) {
        JSObject result = new JSObject();
        result.put("store", "play");
        call.resolve(result);
    }
}
