import { useId, useRef, useState } from 'react';
import Button from './Atoms/Button.jsx';
import TextInput from './Atoms/TextInput/TextInput.jsx';

/**
 * Reusable Smart Kitchen CSV upload strip.
 *
 * @param {{
 *  onUpload: (file: File) => void | Promise<void>;
 *  importCsvTemplateUrl?: string;
 *  onDownloadMealCraftCsvTemplate?: () => void | Promise<void>;
 *  exportUrl?: string;
 *  accept?: string;
 *  className?: string;
 *  uploadBusyLabel?: string;
 * }} props
 */
export default function CSVUploader({
    onUpload,
    importCsvTemplateUrl,
    onDownloadMealCraftCsvTemplate,
    exportUrl,
    accept = '.csv,text/csv',
    className = '',
    uploadBusyLabel = 'Uploading…',
}) {
    const id = useId();
    const inputRef = useRef(null);
    const [file, setFile] = useState(/** @type {File|null} */ (null));
    const [busy, setBusy] = useState(false);
    const [templateBusy, setTemplateBusy] = useState(false);

    async function handleUpload() {
        if (!file || busy) {
            return;
        }
        try {
            setBusy(true);
            await onUpload(file);
            setFile(null);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
        } finally {
            setBusy(false);
        }
    }

    const fileName = file ? file.name : '';

    const linkClass =
        'font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44] underline underline-offset-2';

    return (
        <div className={`flex flex-wrap items-end gap-4 ${className}`.trim()}>
            <div className="relative w-full min-w-0 sm:w-[340px]">
                <TextInput
                    label="Choose file"
                    placeholder="Select a CSV…"
                    value={fileName}
                    onChange={() => {}}
                    className="!max-w-none"
                    id={`${id}-text`}
                    readOnly
                />
                <input
                    ref={inputRef}
                    id={`${id}-file`}
                    type="file"
                    accept={accept}
                    aria-label="Choose CSV file"
                    className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    onChange={(e) => {
                        const next = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                        setFile(next);
                    }}
                />
            </div>

            <Button
                label={busy ? uploadBusyLabel : 'Upload CSV'}
                variant="secondary"
                type="button"
                disabled={!file || busy}
                onClick={handleUpload}
                className="h-[49px] min-h-[49px]"
            />

            <div className="flex flex-wrap items-center gap-4 pb-1">
                {typeof onDownloadMealCraftCsvTemplate === 'function' ? (
                    <button
                        type="button"
                        disabled={templateBusy}
                        className={`${linkClass} disabled:cursor-not-allowed disabled:opacity-50`}
                        onClick={async () => {
                            if (templateBusy) {
                                return;
                            }
                            try {
                                setTemplateBusy(true);
                                await onDownloadMealCraftCsvTemplate();
                            } finally {
                                setTemplateBusy(false);
                            }
                        }}
                    >
                        {templateBusy ? 'Preparing…' : 'Download CSV template'}
                    </button>
                ) : null}
                {importCsvTemplateUrl ? (
                    <a href={importCsvTemplateUrl} className={linkClass}>
                        Download import CSV template
                    </a>
                ) : null}
                {exportUrl ? (
                    <a href={exportUrl} className={linkClass}>
                        Export CSV
                    </a>
                ) : null}
            </div>
        </div>
    );
}
