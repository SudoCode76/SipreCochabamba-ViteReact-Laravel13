const DATE_ONLY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

function isEmptyDate(value) {
  return value === null || value === undefined || value === "";
}

function padDatePart(value) {
  return String(value).padStart(2, "0");
}

function formatDateParts(year, month, day) {
  return `${padDatePart(day)}/${padDatePart(month)}/${year}`;
}

export function formatDate(value) {
  if (isEmptyDate(value)) {
    return "-";
  }

  if (typeof value === "string") {
    const trimmedValue = value.trim();
    const dateOnlyMatch = trimmedValue.match(DATE_ONLY_PATTERN);

    if (dateOnlyMatch) {
      const [, year, month, day] = dateOnlyMatch;
      return formatDateParts(year, month, day);
    }
  }

  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "-";
  }

  return formatDateParts(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

export function formatDateTime(value) {
  if (isEmptyDate(value)) {
    return "-";
  }

  if (typeof value === "string") {
    const trimmedValue = value.trim();
    const dateOnlyMatch = trimmedValue.match(DATE_ONLY_PATTERN);

    if (dateOnlyMatch) {
      const [, year, month, day] = dateOnlyMatch;
      return formatDateParts(year, month, day);
    }
  }

  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "-";
  }

  return `${formatDateParts(
    date.getFullYear(),
    date.getMonth() + 1,
    date.getDate(),
  )} ${padDatePart(date.getHours())}:${padDatePart(date.getMinutes())}`;
}
