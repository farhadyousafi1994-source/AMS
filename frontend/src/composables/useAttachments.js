import { api } from '@/boot/axios'
import { compressImage } from '@/utils/image'

/**
 * Upload picked files onto any record via the universal attachments API.
 * Used by financial forms so every payment/expense/receipt carries its bill,
 * which then shows up in the Invoice Archive.
 */
export async function uploadDocs (type, id, files, kind = 'receipt') {
  const list = Array.isArray(files) ? files : (files ? [files] : [])
  for (const raw of list) {
    const fd = new FormData()
    fd.append('type', type)
    fd.append('id', id)
    fd.append('kind', kind)
    fd.append('file', await compressImage(raw))
    await api.post('/attachments', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  return list.length
}
