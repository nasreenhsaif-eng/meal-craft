/** Leave headroom for multipart form fields under typical 2M PHP post limits. */
export const MEAL_PHOTO_UPLOAD_TARGET_BYTES = 1_500_000;

const MAX_DIMENSION_PX = 2048;
const INITIAL_JPEG_QUALITY = 0.88;
const MIN_JPEG_QUALITY = 0.5;

const COMPRESSIBLE_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);

/**
 * @param {number} width
 * @param {number} height
 * @param {number} maxDimension
 * @returns {{ width: number; height: number }}
 */
function scaleDimensions(width, height, maxDimension) {
    if (Math.max(width, height) <= maxDimension) {
        return { width, height };
    }

    const ratio = maxDimension / Math.max(width, height);

    return {
        width: Math.round(width * ratio),
        height: Math.round(height * ratio),
    };
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {string} type
 * @param {number} quality
 * @returns {Promise<Blob>}
 */
function canvasToBlob(canvas, type, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    resolve(blob);
                    return;
                }

                reject(new Error('Failed to compress image.'));
            },
            type,
            quality,
        );
    });
}

/**
 * Downscale and re-encode large JPEG/PNG/WebP photos so meal library saves stay under PHP post limits.
 *
 * @param {File} file
 * @returns {Promise<File>}
 */
export async function compressMealPhotoForUpload(file) {
    if (!(file instanceof File)) {
        return file;
    }

    if (!COMPRESSIBLE_MIME_TYPES.has(file.type)) {
        return file;
    }

    if (file.size <= MEAL_PHOTO_UPLOAD_TARGET_BYTES) {
        return file;
    }

    const bitmap = await createImageBitmap(file);
    const { width, height } = scaleDimensions(bitmap.width, bitmap.height, MAX_DIMENSION_PX);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    if (!context) {
        bitmap.close();

        return file;
    }

    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    let quality = INITIAL_JPEG_QUALITY;
    let blob = await canvasToBlob(canvas, 'image/jpeg', quality);

    while (blob.size > MEAL_PHOTO_UPLOAD_TARGET_BYTES && quality > MIN_JPEG_QUALITY) {
        quality -= 0.08;
        blob = await canvasToBlob(canvas, 'image/jpeg', quality);
    }

    const baseName = file.name.replace(/\.[^.]+$/i, '') || 'meal-photo';

    return new File([blob], `${baseName}.jpg`, {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}

/**
 * @param {File} file
 * @returns {boolean}
 */
export function isMealPhotoCompressible(file) {
    return file instanceof File && COMPRESSIBLE_MIME_TYPES.has(file.type);
}
