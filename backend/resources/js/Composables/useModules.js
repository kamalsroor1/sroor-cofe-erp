import modulesConfig from '../config/modules.json';

export function useModules() {
  const isModuleEnabled = (keyOrId) => {
    const mod = modulesConfig.modules.find(m => m.id === keyOrId || m.key === keyOrId);
    return mod ? Boolean(mod.enabled) : true;
  };

  const isRouteEnabled = (routePath) => {
    if (!routePath || routePath === '/' || routePath === '/dashboard' || routePath === '/login') return true;
    for (const mod of modulesConfig.modules) {
      if (mod.routes && mod.routes.some(r => routePath.startsWith(r))) {
        return Boolean(mod.enabled);
      }
    }
    return true;
  };

  return {
    modules: modulesConfig.modules,
    isModuleEnabled,
    isRouteEnabled,
  };
}