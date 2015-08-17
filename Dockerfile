FROM ubuntu:14.04.2

# apt
RUN apt-get update 

# gems
ENV DEBIAN_FRONTEND noninteractive
ENV LANG "en_US.UTF-8"
ENV LC_CTYPE "en_US.UTF-8"
RUN locale-gen "en_US.UTF-8" && \
    dpkg-reconfigure locales && \
    apt-get install -y rubygems-integration

# asciidoctor
RUN gem install asciidoctor coderay thread_safe tilt

# render50
RUN apt-get install -y php5-cli
COPY ./render50 /opt/render50
RUN ln -s /opt/render50/bin/render50.sh /usr/local/bin/render50

# asciidoc50
RUN apt-get install -y openjdk-7-jdk
COPY ./asciidoc50 /opt/asciidoc50
RUN ln -s /opt/asciidoc50/bin/asciidoc50 /usr/local/bin/asciidoc50

# home
WORKDIR /root
