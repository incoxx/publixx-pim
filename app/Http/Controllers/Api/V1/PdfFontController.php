<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PdfFont;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfFontController extends Controller
{
    /**
     * Alle Custom-Fonts auflisten.
     */
    public function index(): JsonResponse
    {
        $fonts = PdfFont::orderBy('family_name')->get();

        return response()->json([
            'data' => $fonts->map(fn (PdfFont $font) => [
                'id' => $font->id,
                'family_name' => $font->family_name,
                'variants' => $font->getVariants(),
                'files' => $this->buildFileUrls($font),
                'created_at' => $font->created_at,
            ]),
        ]);
    }

    /**
     * Neue Schriftart hochladen.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'family_name' => 'required|string|max:255',
            'file_regular' => ['required', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if (strtolower($value->getClientOriginalExtension()) !== 'ttf') {
                    $fail('Die Datei muss eine TTF-Schriftart sein.');
                }
            }],
            'file_bold' => ['sometimes', 'nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value && strtolower($value->getClientOriginalExtension()) !== 'ttf') {
                    $fail('Die Datei muss eine TTF-Schriftart sein.');
                }
            }],
            'file_italic' => ['sometimes', 'nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value && strtolower($value->getClientOriginalExtension()) !== 'ttf') {
                    $fail('Die Datei muss eine TTF-Schriftart sein.');
                }
            }],
            'file_bold_italic' => ['sometimes', 'nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value && strtolower($value->getClientOriginalExtension()) !== 'ttf') {
                    $fail('Die Datei muss eine TTF-Schriftart sein.');
                }
            }],
        ]);

        // Sicherstellen, dass das Zielverzeichnis existiert
        $customDir = storage_path('fonts/custom');
        if (!is_dir($customDir)) {
            mkdir($customDir, 0755, true);
        }

        $fontId = Str::uuid()->toString();
        $fileData = [];

        foreach (['regular', 'bold', 'italic', 'bold_italic'] as $variant) {
            $fieldName = 'file_' . $variant;
            if ($request->hasFile($fieldName)) {
                $file = $request->file($fieldName);
                $filename = $fontId . '-' . $variant . '.ttf';
                $file->move($customDir, $filename);
                $fileData[$fieldName] = $filename;
            }
        }

        $font = PdfFont::create([
            'id' => $fontId,
            'family_name' => $request->input('family_name'),
            'file_regular' => $fileData['file_regular'] ?? null,
            'file_bold' => $fileData['file_bold'] ?? null,
            'file_italic' => $fileData['file_italic'] ?? null,
            'file_bold_italic' => $fileData['file_bold_italic'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $font->id,
                'family_name' => $font->family_name,
                'variants' => $font->getVariants(),
            ],
        ], 201);
    }

    /**
     * Font-Datei für Browser-Vorschau ausliefern.
     */
    public function file(PdfFont $pdfFont, string $variant): BinaryFileResponse
    {
        if (!in_array($variant, ['regular', 'bold', 'italic', 'bold_italic'])) {
            abort(404);
        }

        $path = $pdfFont->getAbsolutePath($variant);
        if (!$path || !File::exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Schriftart löschen (inkl. Dateien).
     */
    public function destroy(PdfFont $pdfFont): JsonResponse
    {
        foreach (['regular', 'bold', 'italic', 'bold_italic'] as $variant) {
            $path = $pdfFont->getAbsolutePath($variant);
            if ($path && File::exists($path)) {
                File::delete($path);
            }
        }

        $pdfFont->delete();

        return response()->json(null, 204);
    }

    private function buildFileUrls(PdfFont $font): array
    {
        $urls = [];
        foreach (['regular', 'bold', 'italic', 'bold_italic'] as $variant) {
            $field = 'file_' . $variant;
            if ($font->$field) {
                $urls[$variant] = url("/api/v1/pdf-fonts/{$font->id}/file/{$variant}");
            }
        }
        return $urls;
    }
}
