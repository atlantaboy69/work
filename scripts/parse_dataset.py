import os
import json
import re
import psycopg2
from psycopg2.extras import execute_values
from deep_translator import GoogleTranslator

# 1. Читаем DATABASE_URL из окружения
DATABASE_URL = os.getenv('DATABASE_URL')

if DATABASE_URL and DATABASE_URL.startswith("postgres://"):
    DATABASE_URL = DATABASE_URL.replace("postgres://", "postgresql://", 1)

def get_db_connection():
    if not DATABASE_URL:
        raise ValueError("❌ Ошибка: Переменная окружения DATABASE_URL не найдена!")
    return psycopg2.connect(DATABASE_URL)

# 2. Инициализация переводчиков
translator_to_lat = GoogleTranslator(source='auto', target='la')
translator_to_ru = GoogleTranslator(source='auto', target='ru')

def contains_cyrillic(text: str) -> bool:
    return bool(re.search(r'[\u0400-\u04FF]', text))

def contains_latin(text: str) -> bool:
    return bool(re.search(r'[a-zA-Z]', text))

def clean_text(text: str) -> str:
    text = re.sub(r'^[a-zA-Z0-9а-яА-Я]+\s*[—\-\)]\s*', '', text)
    text = re.sub(r'\s*\((новорожденный|вид снаружи|вид изнутри|правая|левая|рентгенограмма)\)', '', text, flags=re.IGNORECASE)
    return text.strip(" ,.-")

def make_canonical_name(term_lat: str) -> str:
    canonical = term_lat.lower()
    canonical = re.sub(r'[^a-z0-9]', '_', canonical)
    canonical = re.sub(r'_+', '_', canonical).strip('_')
    return canonical

def translate_term(text: str, target_lang: str) -> str:
    try:
        if target_lang == 'la':
            return translator_to_lat.translate(text)
        elif target_lang == 'ru':
            return translator_to_ru.translate(text)
    except Exception as e:
        print(f"⚠️ Ошибка перевода для '{text}': {e}")
        return text
    return text

def parse_label(label: str):
    cleaned = clean_text(label)
    if not cleaned:
        return None

    term_ru = None
    term_lat = None

    parts = re.split(r'[,—\-]', cleaned)
    ru_parts = []
    lat_parts = []

    for part in parts:
        part_str = part.strip()
        if contains_cyrillic(part_str):
            ru_parts.append(part_str)
        elif contains_latin(part_str):
            lat_parts.append(part_str)

    if ru_parts:
        term_ru = " ".join(ru_parts)
    if lat_parts:
        term_lat = " ".join(lat_parts)

    if term_ru and not term_lat:
        print(f"🔄 [RU -> LA] Переводим: '{term_ru}'")
        term_lat = translate_term(term_ru, target_lang='la')

    elif term_lat and not term_ru:
        print(f"🔄 [LA -> RU] Переводим: '{term_lat}'")
        term_ru = translate_term(term_lat, target_lang='ru')

    if not term_ru or not term_lat:
        return None

    canonical = make_canonical_name(term_lat)
    return (term_ru.capitalize(), term_lat.capitalize(), canonical)

def process_dataset(json_file_path: str, cache_file_path: str = "../data/parsed_terms_cache.json"):
    extracted_terms = set()

    # ЕСЛИ КЭШ УЖЕ ЕСТЬ — БЕРЕМ ИЗ НЕГО И НЕ ЖДЕМ ЧАС!
    if os.path.exists(cache_file_path):
        print(f"📦 Найден локальный кэш '{cache_file_path}'! Загружаем данные без перевода...")
        with open(cache_file_path, 'r', encoding='utf-8') as f:
            cached_data = json.load(f)
            extracted_terms = {tuple(item) for item in cached_data}
    else:
        if not os.path.exists(json_file_path):
            print(f"❌ Файл '{json_file_path}' не найден!")
            return

        with open(json_file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)

        for page in data:
            content = page.get("content", {})
            figures = content.get("figures", [])
            for fig in figures:
                labels = fig.get("labels", [])
                for label in labels:
                    parsed = parse_label(label)
                    if parsed:
                        extracted_terms.add(parsed)

        # СОХРАНЯЕМ В КЭШ СРАЗУ ПОСЛЕ ПЕРЕВОДА
        with open(cache_file_path, 'w', encoding='utf-8') as f:
            json.dump(list(extracted_terms), f, ensure_ascii=False, indent=2)
        print(f"💾 Все переведённые термины сохранены в кэш '{cache_file_path}'.")

    print(f"\n✅ Подготовлено уникальных терминов: {len(extracted_terms)}")

    if not extracted_terms:
        print("Нет данных для импорта.")
        return

    # Запись в PostgreSQL
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        insert_query = """
        INSERT INTO anatomy_terms (term_ru, term_lat, canonical_name)
        VALUES %s
        ON CONFLICT (canonical_name) DO NOTHING;
        """

        execute_values(cursor, insert_query, list(extracted_terms))
        conn.commit()
        cursor.close()
        conn.close()
        print("🚀 Все данные успешно занесены в базу данных!")

    except Exception as e:
        print(f"❌ Ошибка базы данных: {e}")
        print("💡 Подсказка: убедитесь, что выполнили в базе: ALTER TABLE anatomy_terms ADD CONSTRAINT anatomy_terms_canonical_name_key UNIQUE (canonical_name);")

if __name__ == "__main__":
    process_dataset("../data/anatomy_structured_dataset.json")