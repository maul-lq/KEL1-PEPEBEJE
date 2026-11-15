---
paths:
  - 'database/migrations/**'
---

# Migrations

## PostgreSQL rollback safety in migrations
Lingkungan utama aplikasi memakai PostgreSQL (`ppbj_kampus`), bukan SQLite. Method `down()` migrasi PostgreSQL wajib menangani data existing terlebih dahulu sebelum mengembalikan constraint/index/PRIMARY KEY/SET NOT NULL (hapus baris NULL sebelum `SET NOT NULL`, isi & deduplikasi nilai sebelum `ADD PRIMARY KEY`, dan gunakan `Schema::getIndexes()` + `dropUnique`/`dropIndex` sesuai tipe constraint sebelum `dropColumn`).
