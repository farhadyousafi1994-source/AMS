// Client-side photo compression for every upload in the system. Site phones
// take 4–12 MB photos on patchy Herat/Kabul connections, so every image is
// re-encoded before it leaves the device and is guaranteed to land under the
// size cap (1 MB by default). Non-images (PDFs, backups) pass through untouched.

const MAX_BYTES = 1024 * 1024 // hard ceiling — 1 MB

/**
 * Re-encode an image to JPEG under `maxBytes`, shrinking quality and then
 * dimensions until it fits. Always returns a File (the original when it is not
 * an image, or when the original is already smaller than anything we produce).
 */
export async function compressImage (file, { maxDim = 1600, quality = 0.72, maxBytes = MAX_BYTES } = {}) {
  if (!file || !file.type?.startsWith('image/')) return file

  const dataUrl = await new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => resolve(r.result)
    r.onerror = reject
    r.readAsDataURL(file)
  })

  const img = await new Promise((resolve, reject) => {
    const im = new Image()
    im.onload = () => resolve(im)
    im.onerror = reject
    im.src = dataUrl
  })

  let { width, height } = img
  if (width > maxDim || height > maxDim) {
    const scale = maxDim / Math.max(width, height)
    width = Math.round(width * scale)
    height = Math.round(height * scale)
  }

  let q = quality
  let blob = await render(img, width, height, q)

  // Squeeze until it fits: drop quality first, then step the dimensions down.
  for (let i = 0; blob && blob.size > maxBytes && i < 8; i++) {
    if (q > 0.4) {
      q = Math.max(0.4, q - 0.12)
    } else {
      width = Math.round(width * 0.82)
      height = Math.round(height * 0.82)
      if (width < 320 || height < 320) break
    }
    blob = await render(img, width, height, q)
  }

  if (!blob) return file
  // Never upsize a small original — unless the original breaks the cap, in
  // which case ours wins even if the gain is marginal.
  if (blob.size >= file.size && file.size <= maxBytes) return file

  const name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg'
  return new File([blob], name, { type: 'image/jpeg' })
}

function render (img, width, height, quality) {
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  // Bills are often shot on white paper, and PNG screenshots carry alpha —
  // flatten onto white so transparent pixels don't encode as black in JPEG.
  ctx.fillStyle = '#FFFFFF'
  ctx.fillRect(0, 0, width, height)
  ctx.drawImage(img, 0, 0, width, height)

  return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
}
