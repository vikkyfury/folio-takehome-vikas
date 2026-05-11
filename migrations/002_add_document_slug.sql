ALTER TABLE documents ADD COLUMN slug TEXT NOT NULL DEFAULT '';
CREATE UNIQUE INDEX idx_documents_slug ON documents(slug);
