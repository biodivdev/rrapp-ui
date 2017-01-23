FROM diogok/java8:zulu

ENV PORT 80
EXPOSE 80

WORKDIR /opt
CMD ["java","-server","-XX:+UseConcMarkSweepGC","-XX:+UseCompressedOops","-XX:+DoEscapeAnalysis","-DPORT=80","-jar","rrapp-ui.jar"]

ADD target/rrapp-ui-0.0.2-standalone.jar /opt/rrapp-ui.jar

