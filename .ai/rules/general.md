---
paths:
  - '**'
---

# General

## DBHub MCP Usage Guidelines
Use Cases:
- Local Development: Schema exploration, query validation, and data debugging with Claude Code, VS Code, Cursor, etc.
- Non-Technical Access: Expose curated, read-only views to non-technical staff via Claude Desktop, VS Code, Cursor, etc.
- Multi-Database Consolidation: Replace separate MCP servers for each database with a single DBHub process
- Production Troubleshooting: Read-only diagnostics with guardrails against runaway queries

## Standar Komentar Kode 3 Tingkat
Setiap membuat berkas, modul, kelas, atau fungsi baru (PHP, JS, CSS, Blade), terapkan standar komentar pada `AGENTS.md` bagian `<project-comment-conventions>`:
1. Header `Informasi File` di paling atas (`Penulis`, `Lisensi: GNU General Public License v3.0 (GPL-3.0)`, `Ringkasan`).
2. `Komentar Deskriptif` tepat di atas class/method/blok utama berisi tujuan, parameter, nilai kembali, dan `Contoh pemanggilan:`.
3. `Komentar Inline` yang menjelaskan alasan desain, aturan domain, dan konsekuensi logika (bukan mengulang kode).
4. Gunakan perspektif orang pertama Bahasa Indonesia tanpa selalu diawali kata `"Saya"` (awali langsung dengan kata kerja aktif orang pertama seperti `menulis`, `menahan`, `membatasi`), dan jangan gunakan kata `"kami"` kecuali diminta secara spesifik oleh pengguna.
