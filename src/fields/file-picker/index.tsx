import React, { ReactElement, useCallback } from 'react';

interface FilePickerProps {
  name: string;
  value: string;
  onChange: (attrName: string, value: string) => void;
  label?: string;
  description?: string;
}

/**
 * Custom file picker field that opens the WordPress Media Library
 * without image-specific behavior (no preview, no image filter).
 */
const FilePicker = ({ name, value, onChange }: FilePickerProps): ReactElement => {
  const filename = value ? value.split('/').pop() : '';

  const openMediaLibrary = useCallback(() => {
    // Access wp.media from the parent window (VB runs in iframe).
    const wpMedia = (window as any).wp?.media || (window.parent as any)?.wp?.media;

    if (!wpMedia) {
      // Fallback: prompt for URL.
      const url = prompt('Enter CSV file URL:');
      if (url) onChange(name, url);
      return;
    }

    const frame = wpMedia({
      title: 'Select CSV File',
      multiple: false,
      library: { type: '' },
      button: { text: 'Use this file' },
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      onChange(name, attachment.url);
    });

    frame.open();
  }, [name, onChange]);

  const clearFile = useCallback(() => {
    onChange(name, '');
  }, [name, onChange]);

  return (
    <div className="dcsve-file-picker">
      {value ? (
        <div className="dcsve-file-picker__selected">
          <div className="dcsve-file-picker__file-icon">📄</div>
          <div className="dcsve-file-picker__file-info">
            <span className="dcsve-file-picker__filename">{filename}</span>
          </div>
          <button
            className="dcsve-file-picker__remove"
            onClick={clearFile}
            title="Remove"
            type="button"
          >
            ✕
          </button>
        </div>
      ) : null}
      <button
        className="dcsve-file-picker__browse"
        onClick={openMediaLibrary}
        type="button"
      >
        {value ? 'Change File' : 'Select CSV File'}
      </button>
    </div>
  );
};

export default FilePicker;
