import json
import os
import datetime

def bump_version():
    backend_dir = r"d:\projects\sroor\backend"
    v_backend = os.path.join(backend_dir, "version.json")
    v_js = os.path.join(backend_dir, "resources", "js", "version.json")

    # Read current version
    current_ver = "1.0.0"
    build_num = 1
    if os.path.exists(v_backend):
        try:
            with open(v_backend, "r", encoding="utf-8") as f:
                data = json.load(f)
                current_ver = data.get("version", "1.0.0")
                build_num = data.get("build_number", 1)
        except Exception:
            pass

    # Increment version by 0.0.1 (patch)
    parts = current_ver.split(".")
    if len(parts) == 3:
        try:
            major = int(parts[0])
            minor = int(parts[1])
            patch = int(parts[2]) + 1
            new_ver = f"{major}.{minor}.{patch}"
        except ValueError:
            new_ver = "1.0.1"
    else:
        new_ver = "1.0.1"

    new_data = {
        "version": new_ver,
        "build_number": build_num + 1,
        "last_build": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    }

    # Save to both paths
    with open(v_backend, "w", encoding="utf-8") as f:
        json.dump(new_data, f, indent=4)

    os.makedirs(os.path.dirname(v_js), exist_ok=True)
    with open(v_js, "w", encoding="utf-8") as f:
        json.dump(new_data, f, indent=4)

    print(f"BUMPED VERSION: {current_ver} -> {new_ver} (Build #{new_data['build_number']})")
    return new_ver

if __name__ == "__main__":
    bump_version()