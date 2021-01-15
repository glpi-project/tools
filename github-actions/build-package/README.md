# Build package Github action

This action can be used to build a plugin's release package.

## Usage

### Inputs

 - `plugin-version`: The tag name corresponding to the release.

### Outputs

 - `package-basename`: Basename of the built package.
 - `package-path`: Path of the built package.

### Example workflow

Following workflow will do these steps each time a tag is pushed:
 - build the plugin's package;
 - draft a new release;
 - attach package to the release.

```yaml
name: "Plugin release"

on:
  push:
    tags:
       - '*'

jobs:
  create-release:
    name: "Create release"
    runs-on: "ubuntu-latest"
    steps:
      - name: "Checkout"
        uses: "actions/checkout@v2"
      - name: "Build package"
        id: "build-package"
        uses: "glpi-project/tools/github-actions/build-package"
        with:
          plugin-version: ${{ github.ref }}
      - name: "Upload package artifact"
        uses: "actions/upload-artifact@v2"
        with:
          name: ${{ steps.build-package.outputs.package-basename }}
          path: ${{ steps.build-package.outputs.package-path }}
      - name: "Create release"
        id: "create-release"
        uses: "actions/create-release@v1"
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: ${{ github.ref }}
          release_name: ${{ github.ref }}
          draft: true
      - name: "Attach package to release"
        uses: "actions/upload-release-asset@v1"
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          upload_url: ${{ steps.create-release.outputs.upload_url }}
          asset_path: ${{ steps.build-package.outputs.package-path }}
          asset_name: ${{ steps.build-package.outputs.package-basename }}
          asset_content_type: " application/x-bzip2"
```
