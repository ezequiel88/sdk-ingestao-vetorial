from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Callable


VALID_BUMPS = {"patch": 0, "minor": 1, "major": 2}
SEMVER_RE = re.compile(r"^(\d+)\.(\d+)\.(\d+)$")


@dataclass(frozen=True)
class SdkConfig:
    key: str
    manifest: str | None
    tag_prefix: str
    current_version: Callable[[Path], str]
    write_version: Callable[[Path, str], None] | None


def repo_root_from_script() -> Path:
    return Path(__file__).resolve().parents[2]


def ensure_semver(version: str) -> tuple[int, int, int]:
    match = SEMVER_RE.match(version.strip())
    if not match:
        raise ValueError(f"Invalid semantic version: {version}")
    return tuple(int(group) for group in match.groups())


def bump_version(current_version: str, bump: str) -> str:
    major, minor, patch = ensure_semver(current_version)
    if bump == "major":
        return f"{major + 1}.0.0"
    if bump == "minor":
        return f"{major}.{minor + 1}.0"
    if bump == "patch":
        return f"{major}.{minor}.{patch + 1}"
    raise ValueError(f"Unsupported bump type: {bump}")


def replace_once(path: Path, pattern: str, replacement: str, flags: int = 0) -> None:
    content = path.read_text(encoding="utf-8")
    updated, count = re.subn(pattern, replacement, content, count=1, flags=flags)
    if count != 1:
        raise ValueError(f"Could not update version in {path}")
    path.write_text(updated, encoding="utf-8")


def read_with_regex(path: Path, pattern: str, flags: int = 0) -> str:
    content = path.read_text(encoding="utf-8")
    match = re.search(pattern, content, flags)
    if not match:
        raise ValueError(f"Could not find version in {path}")
    return match.group(1)


def read_python_version(repo_root: Path) -> str:
    return read_with_regex(repo_root / "sdk/python/pyproject.toml", r'^version = "([^"]+)"$', re.MULTILINE)


def write_python_version(repo_root: Path, version: str) -> None:
    replace_once(repo_root / "sdk/python/pyproject.toml", r'^version = "[^"]+"$', f'version = "{version}"', re.MULTILINE)


def read_js_version(repo_root: Path) -> str:
    return read_with_regex(repo_root / "sdk/js/package.json", r'"version"\s*:\s*"([^"]+)"')


def write_js_version(repo_root: Path, version: str) -> None:
    replace_once(repo_root / "sdk/js/package.json", r'("version"\s*:\s*")[^"]+("\s*,?)', rf'\g<1>{version}\g<2>')


def read_csharp_version(repo_root: Path) -> str:
    return read_with_regex(repo_root / "sdk/csharp/IngestaoVetorial.SDK/IngestaoVetorial.SDK.csproj", r"<Version>([^<]+)</Version>")


def write_csharp_version(repo_root: Path, version: str) -> None:
    replace_once(
        repo_root / "sdk/csharp/IngestaoVetorial.SDK/IngestaoVetorial.SDK.csproj",
        r"(<Version>)([^<]+)(</Version>)",
        rf"\g<1>{version}\g<3>",
    )


def read_go_version(repo_root: Path) -> str:
    result = subprocess.run(["git", "tag", "--list", "sdk/go/v*"], cwd=repo_root, check=True, capture_output=True, text=True)
    versions: list[tuple[int, int, int]] = []
    for line in result.stdout.splitlines():
        tag = line.strip()
        if not tag:
            continue
        version = tag.removeprefix("sdk/go/v")
        try:
            versions.append(ensure_semver(version))
        except ValueError:
            continue
    if not versions:
        return "0.0.0"
    latest = max(versions)
    return f"{latest[0]}.{latest[1]}.{latest[2]}"


def read_flutter_version(repo_root: Path) -> str:
    return read_with_regex(repo_root / "sdk/flutter/pubspec.yaml", r"^version:\s*([^\s]+)$", re.MULTILINE)


def write_flutter_version(repo_root: Path, version: str) -> None:
    replace_once(repo_root / "sdk/flutter/pubspec.yaml", r"^version:\s*[^\s]+$", f"version: {version}", re.MULTILINE)


SDKS: dict[str, SdkConfig] = {
    "python": SdkConfig("python", "sdk/python/pyproject.toml", "sdk-python-v", read_python_version, write_python_version),
    "js": SdkConfig("js", "sdk/js/package.json", "sdk-js-v", read_js_version, write_js_version),
    "csharp": SdkConfig("csharp", "sdk/csharp/IngestaoVetorial.SDK/IngestaoVetorial.SDK.csproj", "sdk-csharp-v", read_csharp_version, write_csharp_version),
    "go": SdkConfig("go", None, "sdk/go/v", read_go_version, None),
    "flutter": SdkConfig("flutter", "sdk/flutter/pubspec.yaml", "sdk-flutter-v", read_flutter_version, write_flutter_version),
}


def parse_front_matter(path: Path) -> tuple[dict[str, str], str]:
    content = path.read_text(encoding="utf-8")
    if not content.startswith("---\n"):
        raise ValueError(f"Changeset {path} must start with YAML front matter")
    marker = "\n---\n"
    end = content.find(marker, 4)
    if end == -1:
        raise ValueError(f"Changeset {path} must contain a closing front matter marker")
    metadata_block = content[4:end]
    body = content[end + len(marker) :].strip()
    metadata: dict[str, str] = {}
    for raw_line in metadata_block.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        key, separator, value = line.partition(":")
        if not separator:
            raise ValueError(f"Invalid changeset line in {path}: {raw_line}")
        sdk_key = key.strip().strip("\"'")
        bump = value.strip().strip("\"'")
        if sdk_key not in SDKS:
            raise ValueError(f"Unknown SDK '{sdk_key}' in {path}")
        if bump not in VALID_BUMPS:
            raise ValueError(f"Invalid bump '{bump}' in {path}; expected patch, minor or major")
        metadata[sdk_key] = bump
    if not metadata:
        raise ValueError(f"Changeset {path} does not declare any SDK bumps")
    return metadata, body


def load_changesets(repo_root: Path, changeset_dir: Path) -> list[dict[str, object]]:
    absolute_dir = repo_root / changeset_dir
    if not absolute_dir.exists():
        return []
    changesets: list[dict[str, object]] = []
    for path in sorted(absolute_dir.glob("*.md")):
        if path.name.lower() == "readme.md":
            continue
        metadata, body = parse_front_matter(path)
        changesets.append({"path": path.relative_to(repo_root).as_posix(), "releases": metadata, "summary": body})
    return changesets


def aggregate_bumps(changesets: list[dict[str, object]]) -> dict[str, str]:
    aggregate: dict[str, str] = {}
    for changeset in changesets:
        releases = changeset["releases"]
        assert isinstance(releases, dict)
        for sdk_key, bump in releases.items():
            assert isinstance(sdk_key, str)
            assert isinstance(bump, str)
            current = aggregate.get(sdk_key)
            if current is None or VALID_BUMPS[bump] > VALID_BUMPS[current]:
                aggregate[sdk_key] = bump
    return aggregate


def build_plan(repo_root: Path, changeset_dir: Path) -> dict[str, object]:
    changesets = load_changesets(repo_root, changeset_dir)
    aggregated = aggregate_bumps(changesets)
    releases: list[dict[str, str]] = []
    for sdk_key in SDKS:
        bump = aggregated.get(sdk_key)
        if bump is None:
            continue
        config = SDKS[sdk_key]
        current_version = config.current_version(repo_root)
        next_version = bump_version(current_version, bump)
        releases.append({
            "sdk": sdk_key,
            "bump": bump,
            "current_version": current_version,
            "next_version": next_version,
            "tag": f"{config.tag_prefix}{next_version}",
            "manifest": config.manifest or "",
        })
    return {"changesets": changesets, "releases": releases, "has_releases": bool(releases)}


def write_plan(plan: dict[str, object], output_path: Path) -> None:
    output_path.write_text(json.dumps(plan, indent=2) + "\n", encoding="utf-8")


def load_plan(plan_path: Path) -> dict[str, object]:
    return json.loads(plan_path.read_text(encoding="utf-8"))


def print_github_outputs(plan: dict[str, object]) -> None:
    output_path = os.environ.get("GITHUB_OUTPUT")
    if not output_path:
        raise RuntimeError("GITHUB_OUTPUT is not set")
    releases = plan["releases"]
    assert isinstance(releases, list)
    release_targets = ",".join(release["sdk"] for release in releases)
    release_tags = ",".join(release["tag"] for release in releases)
    with Path(output_path).open("a", encoding="utf-8") as handle:
        handle.write(f"has_releases={'true' if releases else 'false'}\n")
        handle.write(f"release_count={len(releases)}\n")
        handle.write(f"release_targets={release_targets}\n")
        handle.write(f"release_tags={release_tags}\n")


def apply_plan(repo_root: Path, plan_path: Path) -> None:
    plan = load_plan(plan_path)
    releases = plan["releases"]
    changesets = plan["changesets"]
    assert isinstance(releases, list)
    assert isinstance(changesets, list)
    for release in releases:
        sdk_key = release["sdk"]
        next_version = release["next_version"]
        assert isinstance(sdk_key, str)
        assert isinstance(next_version, str)
        config = SDKS[sdk_key]
        if config.write_version is not None:
            config.write_version(repo_root, next_version)
    for changeset in changesets:
        relative_path = changeset["path"]
        assert isinstance(relative_path, str)
        (repo_root / relative_path).unlink(missing_ok=False)


def print_tags(plan_path: Path) -> None:
    plan = load_plan(plan_path)
    releases = plan["releases"]
    assert isinstance(releases, list)
    for release in releases:
        print(release["tag"])


def is_release_relevant_sdk_path(relative_path: str) -> bool:
    normalized = relative_path.replace("\\", "/")
    excluded_markers = ("/bin/", "/obj/", "/vendor/", "/.egg-info/", "/dist/", "/.dart_tool/", "/build/")
    if any(marker in normalized for marker in excluded_markers):
        return False
    if normalized.endswith("README.md"):
        return False
    top_level = normalized.split("/", 1)[0]
    return top_level in SDKS


def changed_files(repo_root: Path, base_ref: str) -> list[str]:
    result = subprocess.run(["git", "diff", "--name-only", f"{base_ref}...HEAD"], cwd=repo_root, check=True, capture_output=True, text=True)
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def require_changeset(repo_root: Path, base_ref: str) -> int:
    files = changed_files(repo_root, base_ref)
    has_sdk_changes = any(is_release_relevant_sdk_path(path) for path in files)
    has_changeset = any(path.startswith(".changeset/") and path.endswith(".md") and not path.lower().endswith("readme.md") for path in files)
    if has_sdk_changes and not has_changeset:
        print("SDK changes detected without a changeset file in .changeset/.", file=sys.stderr)
        return 1
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="SDK release helpers")
    parser.add_argument("--repo-root", default=str(repo_root_from_script()))
    subparsers = parser.add_subparsers(dest="command", required=True)
    plan_parser = subparsers.add_parser("plan", help="Generate the pending release plan")
    plan_parser.add_argument("--changeset-dir", default=".changeset")
    plan_parser.add_argument("--output-json")
    plan_parser.add_argument("--github-output", action="store_true")
    apply_parser = subparsers.add_parser("apply", help="Apply a release plan to manifest files")
    apply_parser.add_argument("--plan-file", required=True)
    print_tags_parser = subparsers.add_parser("print-tags", help="Print release tags from a plan")
    print_tags_parser.add_argument("--plan-file", required=True)
    require_parser = subparsers.add_parser("require-changeset", help="Fail if SDK changes are missing a changeset")
    require_parser.add_argument("--diff-base", required=True)
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    repo_root = Path(args.repo_root).resolve()
    try:
        if args.command == "plan":
            plan = build_plan(repo_root, Path(args.changeset_dir))
            if args.output_json:
                write_plan(plan, repo_root / args.output_json)
            else:
                print(json.dumps(plan, indent=2))
            if args.github_output:
                print_github_outputs(plan)
            return 0
        if args.command == "apply":
            apply_plan(repo_root, repo_root / args.plan_file)
            return 0
        if args.command == "print-tags":
            print_tags(repo_root / args.plan_file)
            return 0
        if args.command == "require-changeset":
            return require_changeset(repo_root, args.diff_base)
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        return 1
    parser.print_help()
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
