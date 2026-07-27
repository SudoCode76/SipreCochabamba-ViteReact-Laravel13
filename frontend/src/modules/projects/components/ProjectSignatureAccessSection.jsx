import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Search, Users } from "lucide-react";

import { Input } from "@/components/ui/input";
import { projectService } from "../services/project.service";

function mergePeople(people, authorizedUsers) {
  const byId = new Map();

  people.forEach((person) => byId.set(Number(person.id), person));
  authorizedUsers.forEach((person) => {
    if (!byId.has(Number(person.id))) {
      byId.set(Number(person.id), person);
    }
  });

  return [...byId.values()].sort((a, b) => (a.full_name || "").localeCompare(b.full_name || ""));
}

export default function ProjectSignatureAccessSection({
  projectId,
  people = [],
  draftValue = null,
  onDraftChange = null,
  creatorId = 0,
}) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["project-signature-access", projectId],
    queryFn: () => projectService.signatureAccess(projectId),
    enabled: Boolean(projectId),
  });
  const configuration = data?.data;

  if (!projectId) {
    const normalizedCreatorId = Number(creatorId);
    const selectedIds = [...new Set([
      normalizedCreatorId,
      ...(draftValue?.user_ids ?? []).map(Number),
    ].filter(Boolean))];
    const creator = people.find((person) => Number(person.id) === normalizedCreatorId) ?? {
      id: normalizedCreatorId,
      full_name: "Usuario creador",
      is_creator: true,
    };
    const authorizedUsers = mergePeople(
      people.filter((person) => selectedIds.includes(Number(person.id))),
      [creator],
    );

    return (
      <ProjectSignatureAccessEditor
        people={people}
        onDraftChange={onDraftChange}
      configuration={{
          mode: "selected",
          creator,
          authorized_users: authorizedUsers,
          can_manage: true,
        }}
      />
    );
  }

  if (isLoading) {
    return (
      <section className="flex items-center justify-center rounded-2xl border border-border/70 p-6 text-sm text-muted-foreground">
        <Loader2 className="mr-2 size-4 animate-spin" /> Cargando usuarios autorizados...
      </section>
    );
  }

  if (isError || !configuration) {
    return <section className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">No se pudieron cargar los usuarios autorizados.</section>;
  }

  const editorKey = `${configuration.root_project_id}-${configuration.mode}-${configuration.authorized_users.map((user) => user.id).join("-")}`;

  return (
    <ProjectSignatureAccessEditor
      key={editorKey}
      people={people}
      configuration={configuration}
      onDraftChange={onDraftChange}
    />
  );
}

function ProjectSignatureAccessEditor({ people, configuration, onDraftChange = null }) {
  const [selectedIds, setSelectedIds] = useState(
    configuration.authorized_users.map((user) => Number(user.id)),
  );
  const [search, setSearch] = useState("");

  const creatorId = Number(configuration.creator?.id || 0);
  const users = useMemo(
    () => mergePeople(people, configuration.authorized_users),
    [configuration.authorized_users, people],
  );
  const filteredUsers = useMemo(() => {
    const term = search.trim().toLowerCase();

    if (!term) {
      return users;
    }

    return users.filter((user) => `${user.full_name || ""} ${user.username || ""}`.toLowerCase().includes(term));
  }, [search, users]);

  const toggleUser = (userId) => {
    if (!configuration?.can_manage || configuration?.locked) {
      return;
    }

    setSelectedIds((current) => {
      if (current.includes(userId) && current.length === 1) {
        return current;
      }

      const next = current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId];
      onDraftChange?.({ mode: "selected", user_ids: next });

      return next;
    });
  };

  return (
    <section className="rounded-2xl border border-border/70 bg-muted/20 p-5">
      <div className="flex items-start gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white">
          <Users className="size-5" />
        </span>
        <div>
          <h3 className="font-semibold text-foreground">Usuarios con permiso para modificar el proyecto</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Solo los usuarios seleccionados podrán editar datos e ítems, actualizar precios, recalcular,
            crear versiones y firmar. Los demás usuarios únicamente podrán ver el proyecto.
          </p>
        </div>
      </div>

      {configuration.locked ? (
        <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
          La lista quedó bloqueada por la primera firma digital. Cree una nueva versión para cambiarla.
        </p>
      ) : null}

      <div className="mt-5">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por nombre o usuario" className="h-11 rounded-xl pl-9" />
          </div>
          <div className="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-2xl border border-border/70 bg-background p-3">
            {filteredUsers.map((user) => {
              const userId = Number(user.id);
              const isCreator = userId === creatorId;

              return (
                <label key={userId} className="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-muted/60">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(userId)}
                    onChange={() => toggleUser(userId)}
                    disabled={!configuration.can_manage || configuration.locked}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-foreground">{user.full_name || `Usuario ${userId}`}</span>
                    <span className="block truncate text-xs text-muted-foreground">{user.username || "Sin usuario"}{user.status && user.status !== "AC" ? " · Inactivo" : ""}</span>
                  </span>
                  <span className="flex items-center gap-2">
                    {!user.has_signature_image ? <span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-700">SIN FIRMA</span> : null}
                    {isCreator ? <span className="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-semibold text-sky-700">CREADOR</span> : null}
                  </span>
                </label>
              );
            })}
          </div>
      </div>

    </section>
  );
}
