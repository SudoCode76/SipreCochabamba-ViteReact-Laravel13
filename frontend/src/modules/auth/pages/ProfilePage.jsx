import { useEffect, useRef, useState } from "react";
import { Loader2, PenLine, Trash2, Upload, X } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useToast } from "@/components/ui/toast";
import { apiOrigin } from "@/lib/api/client";
import { authService } from "@/modules/auth/services/auth.service";

const MIN_CROP_PERCENT = 8;
const CROP_HANDLES = ["nw", "n", "ne", "e", "se", "s", "sw", "w"];

function userName(user) {
  return user?.full_name || user?.nombre || user?.username || "Usuario";
}

function signatureImageUrl(value) {
  if (!value) {
    return "";
  }

  try {
    const apiUrl = new URL(apiOrigin);
    const url = new URL(value, apiOrigin);

    if (url.hostname === apiUrl.hostname && !url.port && apiUrl.port) {
      url.port = apiUrl.port;
    }

    return url.toString();
  } catch {
    return value;
  }
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

function pointerPercent(event, element) {
  const rect = element.getBoundingClientRect();

  return {
    x: clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100),
    y: clamp(((event.clientY - rect.top) / rect.height) * 100, 0, 100),
  };
}

function resizeCrop(startRect, handle, pointer) {
  let left = startRect.x;
  let top = startRect.y;
  let right = startRect.x + startRect.w;
  let bottom = startRect.y + startRect.h;

  if (handle.includes("w")) {
    left = clamp(pointer.x, 0, right - MIN_CROP_PERCENT);
  }
  if (handle.includes("e")) {
    right = clamp(pointer.x, left + MIN_CROP_PERCENT, 100);
  }
  if (handle.includes("n")) {
    top = clamp(pointer.y, 0, bottom - MIN_CROP_PERCENT);
  }
  if (handle.includes("s")) {
    bottom = clamp(pointer.y, top + MIN_CROP_PERCENT, 100);
  }

  return {
    x: left,
    y: top,
    w: right - left,
    h: bottom - top,
  };
}

function handleClassName(handle) {
  const vertical = handle.includes("n") ? "-top-1.5" : handle.includes("s") ? "-bottom-1.5" : "top-1/2 -translate-y-1/2";
  const horizontal = handle.includes("w") ? "-left-1.5" : handle.includes("e") ? "-right-1.5" : "left-1/2 -translate-x-1/2";
  const cursor = {
    n: "cursor-ns-resize",
    s: "cursor-ns-resize",
    e: "cursor-ew-resize",
    w: "cursor-ew-resize",
    nw: "cursor-nwse-resize",
    se: "cursor-nwse-resize",
    ne: "cursor-nesw-resize",
    sw: "cursor-nesw-resize",
  }[handle];

  return `absolute ${vertical} ${horizontal} size-3 rounded-[3px] border border-violet-500 bg-white shadow ${cursor}`;
}

export default function ProfilePage() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const fileInputRef = useRef(null);
  const cropAreaRef = useRef(null);
  const [error, setError] = useState("");
  const [selectedFileName, setSelectedFileName] = useState("");
  const [previewUrl, setPreviewUrl] = useState("");
  const [previewImage, setPreviewImage] = useState(null);
  const [crop, setCrop] = useState({ x: 10, y: 10, w: 80, h: 80 });
  const [drag, setDrag] = useState(null);

  const { data: profile, isLoading, isError } = useQuery({
    queryKey: ["profile"],
    queryFn: authService.getProfile,
    retry: false,
  });

  const user = profile?.data?.user ?? profile?.data ?? {};
  const resolvedSignatureUrl = signatureImageUrl(user?.signature_image_url);

  const refreshProfile = () => {
    void queryClient.invalidateQueries({ queryKey: ["profile"] });
    void queryClient.invalidateQueries({ queryKey: ["auth-user"] });
  };

  const uploadMutation = useMutation({
    mutationFn: authService.uploadSignatureImage,
    onSuccess: () => {
      setError("");
      toast.success("Firma guardada correctamente.");
      refreshProfile();
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
      clearSelectedFile();
    },
    onError: (uploadError) => {
      setError(uploadError?.response?.data?.message || "No se pudo guardar la firma.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: authService.deleteSignatureImage,
    onSuccess: () => {
      setError("");
      toast.success("Firma eliminada correctamente.");
      refreshProfile();
    },
    onError: (deleteError) => {
      setError(deleteError?.response?.data?.message || "No se pudo eliminar la firma.");
    },
  });

  const handleFileChange = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    if (!["image/png", "image/jpeg"].includes(file.type)) {
      setError("La firma debe ser una imagen JPG o PNG.");
      return;
    }

    setError("");
    setSelectedFileName(file.name);
    setCrop({ x: 10, y: 10, w: 80, h: 80 });
    setDrag(null);
    setPreviewImage(null);
    setPreviewUrl((currentUrl) => {
      if (currentUrl) {
        URL.revokeObjectURL(currentUrl);
      }

      return URL.createObjectURL(file);
    });
  };

  const clearSelectedFile = () => {
    setSelectedFileName("");
    setPreviewImage(null);
    setCrop({ x: 10, y: 10, w: 80, h: 80 });
    setDrag(null);
    setPreviewUrl((currentUrl) => {
      if (currentUrl) {
        URL.revokeObjectURL(currentUrl);
      }

      return "";
    });

    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const handleSaveCrop = async () => {
    if (!previewImage) {
      return;
    }

    const canvas = document.createElement("canvas");
    const sx = Math.round((crop.x / 100) * previewImage.naturalWidth);
    const sy = Math.round((crop.y / 100) * previewImage.naturalHeight);
    const sw = Math.round((crop.w / 100) * previewImage.naturalWidth);
    const sh = Math.round((crop.h / 100) * previewImage.naturalHeight);
    canvas.width = sw;
    canvas.height = sh;
    canvas.getContext("2d").drawImage(previewImage, sx, sy, sw, sh, 0, 0, sw, sh);

    canvas.toBlob((blob) => {
      if (!blob) {
        setError("No se pudo preparar la imagen recortada.");
        return;
      }

      const fileName = selectedFileName.replace(/\.[^.]+$/, "") || "firma";
      const croppedFile = new File([blob], `${fileName}-recortada.png`, { type: "image/png" });
      uploadMutation.mutate(croppedFile);
    }, "image/png");
  };

  const beginCropDrag = (event, type, handle = null) => {
    if (!cropAreaRef.current) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.currentTarget.setPointerCapture?.(event.pointerId);
    setDrag({
      type,
      handle,
      pointerId: event.pointerId,
      startPointer: pointerPercent(event, cropAreaRef.current),
      startRect: crop,
    });
  };

  const handleCropPointerMove = (event) => {
    if (!drag || !cropAreaRef.current) {
      return;
    }

    const pointer = pointerPercent(event, cropAreaRef.current);

    if (drag.type === "move") {
      const dx = pointer.x - drag.startPointer.x;
      const dy = pointer.y - drag.startPointer.y;

      setCrop({
        ...drag.startRect,
        x: clamp(drag.startRect.x + dx, 0, 100 - drag.startRect.w),
        y: clamp(drag.startRect.y + dy, 0, 100 - drag.startRect.h),
      });
      return;
    }

    setCrop(resizeCrop(drag.startRect, drag.handle, pointer));
  };

  const endCropDrag = (event) => {
    if (drag?.pointerId === event.pointerId) {
      setDrag(null);
    }
  };

  useEffect(() => {
    if (!previewUrl) {
      return undefined;
    }

    const image = new Image();
    image.onload = () => setPreviewImage(image);
    image.src = previewUrl;

    return () => {
      image.onload = null;
    };
  }, [previewUrl]);

  useEffect(() => () => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
  }, [previewUrl]);

  if (isLoading) {
    return (
      <div className="flex h-[420px] items-center justify-center">
        <Loader2 className="size-5 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (isError) {
    return (
      <Alert variant="destructive" className="mx-auto max-w-xl rounded-[24px] border-destructive/20 bg-destructive/5">
        <AlertDescription>No se pudo cargar el perfil.</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="flex justify-center py-4">
      <Card className="w-full max-w-4xl border border-border/70 bg-white/86 shadow-[0_24px_90px_rgba(15,23,42,0.08)] backdrop-blur">
        <CardHeader>
          <CardTitle className="flex items-center gap-3 text-3xl tracking-[-0.05em]">
            <span className="flex size-11 items-center justify-center rounded-full bg-foreground text-background">
              <PenLine className="size-5" />
            </span>
            Perfil
          </CardTitle>
          <p className="text-sm text-muted-foreground">{userName(user)}</p>
        </CardHeader>

        <CardContent className="space-y-6 px-6 pb-6 sm:px-8 sm:pb-8">
          {error ? (
            <Alert variant="destructive" className="rounded-[22px] border-destructive/20 bg-destructive/5">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}

          <section className="rounded-[28px] border border-border/70 bg-muted/25 p-5">
            <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="text-lg font-semibold">Firma física</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Esta imagen se estampa en las copias visuales de PDFs firmados.
                </p>
              </div>

              {resolvedSignatureUrl ? (
                <img
                  src={resolvedSignatureUrl}
                  alt="Firma física"
                  className="h-24 max-w-64 rounded-2xl border border-border/70 bg-white object-contain p-3"
                />
              ) : (
                <div className="flex h-24 w-64 items-center justify-center rounded-2xl border border-dashed border-border/80 bg-white text-sm text-muted-foreground">
                  Sin firma cargada
                </div>
              )}
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
              <div className="flex-1">
                <Label htmlFor="signatureImage" className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  Imagen JPG o PNG
                </Label>
                <Input
                  ref={fileInputRef}
                  id="signatureImage"
                  type="file"
                  accept="image/png,image/jpeg"
                  className="mt-2 h-12 rounded-2xl border-border/80 bg-background/90"
                  onChange={handleFileChange}
                  disabled={uploadMutation.isPending}
                />
              </div>

              <Button type="button" disabled={uploadMutation.isPending} onClick={() => fileInputRef.current?.click()} className="h-12 rounded-full bg-foreground px-5 text-background hover:bg-foreground/90">
                <Upload data-icon="inline-start" />
                Seleccionar
              </Button>

              {resolvedSignatureUrl ? (
                <Button type="button" variant="outline" disabled={deleteMutation.isPending} onClick={() => deleteMutation.mutate()} className="h-12 rounded-full">
                  {deleteMutation.isPending ? <Loader2 className="animate-spin" data-icon="inline-start" /> : <Trash2 data-icon="inline-start" />}
                  Eliminar
                </Button>
              ) : null}
            </div>

            {previewUrl ? (
              <div className="mt-5 rounded-[24px] border border-border/70 bg-white p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <h3 className="text-sm font-semibold">Recortar firma</h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Arrastra el recuadro o sus esquinas antes de guardar.
                    </p>
                  </div>
                  <Button type="button" variant="ghost" size="icon" onClick={clearSelectedFile} disabled={uploadMutation.isPending} aria-label="Cancelar recorte">
                    <X className="size-4" />
                  </Button>
                </div>

                <div
                  ref={cropAreaRef}
                  className="relative mt-4 max-h-[520px] overflow-hidden rounded-2xl border border-border/70 bg-muted/30 touch-none select-none"
                  onPointerMove={handleCropPointerMove}
                  onPointerUp={endCropDrag}
                  onPointerCancel={endCropDrag}
                >
                  <img src={previewUrl} alt="Recorte de firma" className="block max-h-[520px] w-full object-contain" draggable={false} />
                  <div
                    className="absolute border-2 border-violet-500 bg-transparent shadow-[0_0_0_9999px_rgba(0,0,0,0.32)]"
                    style={{
                      left: `${crop.x}%`,
                      top: `${crop.y}%`,
                      width: `${crop.w}%`,
                      height: `${crop.h}%`,
                    }}
                    onPointerDown={(event) => beginCropDrag(event, "move")}
                    role="presentation"
                  >
                    {CROP_HANDLES.map((handle) => (
                      <button
                        key={handle}
                        type="button"
                        className={handleClassName(handle)}
                        onPointerDown={(event) => beginCropDrag(event, "resize", handle)}
                        aria-label={`Redimensionar recorte ${handle}`}
                      />
                    ))}
                  </div>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                  <Button type="button" variant="outline" className="rounded-full" onClick={clearSelectedFile} disabled={uploadMutation.isPending}>
                    Cancelar
                  </Button>
                  <Button type="button" className="rounded-full bg-foreground text-background hover:bg-foreground/90" onClick={handleSaveCrop} disabled={uploadMutation.isPending || !previewImage}>
                    {uploadMutation.isPending ? <Loader2 className="animate-spin" data-icon="inline-start" /> : <Upload data-icon="inline-start" />}
                    {uploadMutation.isPending ? "Guardando..." : "Guardar firma"}
                  </Button>
                </div>
              </div>
            ) : null}
          </section>
        </CardContent>
      </Card>
    </div>
  );
}
