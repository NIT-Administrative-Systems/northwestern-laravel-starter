# Applying Upstream Updates

After running `composer create-project`, your application is an independent Git repository with no connection to the starter. When the starter ships fixes or new features, you can optionally cherry-pick those commits into your project.

## Knowing When Updates Are Available

Watch the starter’s repository on GitHub for releases (**Watch → Custom → Releases**) and GitHub notifies you of each one. Every release links to its section of the [changelog](https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/blob/main/CHANGELOG.md).

The version your project started from is the newest one in the `CHANGELOG.md` that `composer create-project` gave you; note it before you replace that file with your own. To see everything the starter has changed since then, compare it with `main`, putting your version in place of `v3.0.0`:

```text
https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/compare/v3.0.0...main
```

## Setup

Add the starter as a Git remote (one-time):

```bash
git remote add starter https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter.git
git fetch starter
```

## Cherry-Picking Commits

1. **Fetch the latest starter commits**

   ```bash
   git fetch starter
   ```

2. **Review what’s changed**

   Browse the starter’s commit log to find the commits you want:

   ```bash
   git log starter/main --oneline
   ```

3. **Cherry-pick specific commits**

   Apply individual commits to your current branch:

   ```bash
   git cherry-pick <commit-hash>
   ```

   Or apply a range of commits:

   ```bash
   git cherry-pick <oldest-hash>^..<newest-hash>
   ```

4. **Resolve any conflicts**

   If the cherry-pick encounters conflicts (because your project has diverged), Git will pause and let you resolve them:

   ```bash
   # Review conflicts
   git status


   # Fix conflicting files, then continue
   git add .
   git cherry-pick --continue
   ```

   If a commit doesn’t apply cleanly and isn’t worth the effort, you can skip it and move on to the next one in the range:

   ```bash
   git cherry-pick --skip
   ```

   To give up on the whole cherry-pick instead, run `git cherry-pick --abort`. It returns the branch to where it was before you started, dropping any commits from the range that were already applied.

## Tips

* **Review the release notes first.** The comparison between your version and the latest release shows exactly what changed. Some commits may depend on others or require configuration changes.
* **Cherry-pick in order** when applying multiple related commits. Applying them out of sequence increases the likelihood of conflicts.

> **Tip**
>
> If a cherry-pick produces too many conflicts, it may be easier to read the commit diff (`git show <hash>`) and apply the changes manually. This is common when your project has significantly restructured a file the starter also changed.

## Alternative: Patch Files

If you prefer not to add the starter as a remote, you can apply changes from patch files:

```bash
# In a clone of the starter repo, generate patches
git format-patch <from>..<to> --stdout > upstream-fix.patch


# In your project repo, apply the patch
git am upstream-fix.patch
```
