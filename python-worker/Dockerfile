FROM python:3.13-slim-bookworm
WORKDIR /worker
COPY worker.py .
USER nobody
EXPOSE 8081
CMD ["python", "worker.py"]
