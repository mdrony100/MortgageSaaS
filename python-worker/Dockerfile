FROM python:3.13-slim-bookworm
WORKDIR /worker
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY worker.py parse_document.py ./
USER nobody
EXPOSE 8081
CMD ["python", "worker.py"]
